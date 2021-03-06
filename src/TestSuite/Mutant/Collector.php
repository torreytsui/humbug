<?php

/**
 * Class collecting all mutants and their results.
 *
 * @category   Humbug
 * @package    Humbug
 * @copyright  Copyright (c) 2015 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    https://github.com/padraic/humbug/blob/master/LICENSE New BSD License
 * @author     Thibaud Fabre
 */
namespace Humbug\TestSuite\Mutant;

use Humbug\Mutant;

class Collector
{

    /**
     * @var int Total mutant count collected
     */
    private $totalCount = 0;

    /**
     * @var int Count of mutants not covered by a test case.
     */
    private $shadowCount = 0;

    /**
     * @var Mutant[] Mutants not covered by a test case.
     */
    private $shadows = [];

    /**
     * @var int Count of mutants killed by a test case.
     */
    private $killedCount = 0;

    /**
     * @var Result[] Mutants killed by a test case.
     */
    private $killed = [];

    /**
     * @var int Count of mutants that timed out.
     */
    private $timeoutCount = 0;

    /**
     * @var Result[] Mutants that resulted in a timeout.
     */
    private $timeouts = [];

    /**
     * @var int Count of mutants that triggered an error.
     */
    private $errorCount = 0;

    /**
     * @var Result[] Mutants that triggered an error.
     */
    private $errors = [];

    /**
     * @var int Count of mutants that escaped tests.
     */
    private $escapeCount = 0;

    /**
     * @var Result[] Mutants that escaped tests.
     */
    private $escaped = [];

    /**
     * Collects a mutant result.
     *
     * @param Result $result
     */
    public function collect(Result $result)
    {
        $this->totalCount++;

        if ($result->isTimeout()) {
            $this->timeoutCount++;
            $this->timeouts[] = $result;
        } elseif ($result->isError()) {
            $this->errorCount++;
            $this->errors[] = $result;
        } elseif ($result->isKill()) {
            $this->killedCount++;
            $this->killed[] = $result;
        } else {
            $this->escapeCount++;
            $this->escaped[] = $result;
        }
    }

    /**
     * Collects a shadow mutant.
     */
    public function collectShadow(Mutant $mutant = null)
    {
        $this->totalCount++;
        $this->shadowCount++;
        if (!is_null($mutant)) {
            $this->shadows[] = $mutant;
        }
    }

    /**
     * @return int Total count of collected mutants.
     */
    public function getTotalCount()
    {
        return $this->totalCount;
    }

    /**
     * @return int Measurable count of mutants.
     */
    public function getMeasurableTotal()
    {
        return $this->totalCount - $this->shadowCount;
    }

    /**
     * @return int Count of mutants that were covered by a test.
     */
    public function getVanquishedTotal()
    {
        return $this->killedCount + $this->timeoutCount + $this->errorCount;
    }

    /**
     * @return int Count of mutants that were not covered by a test
     */
    public function getShadowCount()
    {
        return $this->shadowCount;
    }

    /**
     * @return Mutant[] List of mutants not covered by tests.
     */
    public function getShadows()
    {
        return $this->shadows;
    }
    
    /**
     * @return int Count of mutants successfully killed by tests.
     */
    public function getKilledCount()
    {
        return $this->killedCount;
    }

    /**
     * @return Result[] List of mutants successfully killed by tests.
     */
    public function getKilled()
    {
        return $this->killed;
    }

    /**
     * @return int Count of mutants that resulted in a timeout.
     */
    public function getTimeoutCount()
    {
        return $this->timeoutCount;
    }

    /**
     * @return Result[] List of mutants that resulted in a timeout.
     */
    public function getTimeouts()
    {
        return $this->timeouts;
    }

    /**
     * @return int Count of mutants that resulted in an error.
     */
    public function getErrorCount()
    {
        return $this->errorCount;
    }

    /**
     * @return Result[] List of mutants that triggered an error.
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return int Count of mutants that escaped test cases.
     */
    public function getEscapeCount()
    {
        return $this->escapeCount;
    }

    /**
     * @return Result[] List of mutants that escaped test cases.
     */
    public function getEscaped()
    {
        return $this->escaped;
    }

    /**
     * Returns all collected mutants as arrays, grouped by their result status.
     *
     * @return array
     */
    public function toGroupedMutantArray()
    {
        return [
            'uncovered' => $this->createUncoveredGroup($this->shadows),
            'escaped' => $this->createGroup($this->escaped),
            'errored' => $this->createGroup($this->errors),
            'timeouts' => $this->createGroup($this->timeouts),
            'killed' => $this->createGroup($this->killed)
        ];
    }

    private function createGroup(array $mutants)
    {
        $group = [];

        foreach ($mutants as $mutant) {
            $mutantData = $mutant->toArray();

            $stderr = explode(PHP_EOL, $mutantData['stderr'], 2);
            $mutantData['stderr'] = $stderr[0];

            if (isset($mutantData['stdout'])) {
                $stdout = explode(PHP_EOL, $mutantData['stdout'], 2);
                $mutantData['stdout'] = $stdout[0];
            }

            $group[] = $mutantData;
        }

        return $group;
    }

    private function createUncoveredGroup(array $mutants)
    {
        $group = [];

        foreach ($mutants as $mutant) {
            $mutantData = $mutant->toArray();
            $uncovered = [
                'file' => $mutantData['file'],
                'mutator' => $mutantData['mutator'],
                'class' => $mutantData['class'],
                'method' => $mutantData['method'],
                'line' => $mutantData['line'],
            ];
            if (in_array($uncovered, $group)) {
                continue;
            }
            
            $group[] = $uncovered;
        }

        return $group;
    }
    
    public function toGroupedFileArray()
    {
        $types = [
            'escaped' => $this->escaped,
            'errors' => $this->errors,
            'timeouts' => $this->timeouts,
            'killed' => $this->killed,
            'shadows' => $this->shadows
        ];

        $group = [];

        foreach ($types as $type => $collection) {
            foreach ($collection as $result) {
                if ($result instanceof Result) {
                    $mutant = $result->getMutant();
                } else {
                    $mutant = $result;
                }
                $file = $mutant->getMutation()->getFile();

                if (!isset($group[$file])) {
                    $group[$file] = [];
                }

                $item = [
                    'result' => serialize($result),
                    'isShadow' => false
                ];
                
                switch ($type) {
                    case 'shadows':
                        $item['isShadow'] = true;
                        break;
                    default:
                        break;
                }
                $group[$file][] = $item;
            }
        }

        return $group;
    }

    /**
     * Returns all collected uncovered/shadows as arrays.
     *
     * @return array
     */
    public function getGroupedShadowArray()
    {
        return [
            'shadows' => $this->createGroup($this->shadows)
        ];
    }
}

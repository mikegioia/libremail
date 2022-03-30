<?php

namespace App\Messages;

use stdClass;

class Names
{
    public const MAX_LEN = 18;
    public const UTF8 = 'utf-8';

    /**
     * Parses a list of names in the "from" header and their
     * corresponding "seen" flags, and returns an HTML formatted
     * string. The $names list is an array of all from headers in
     * order on the thread, and the $seens list is the corresponding
     * seen flag for each message.
     *
     * @return string
     */
    public function get(array $names, array $seens, bool $hasDraft)
    {
        $i = count($names);

        // Final set will have at most three, and at least two names
        $list = [
            1 => $this->getRow(array_shift($names), array_shift($seens), 1),
            2 => null,
            3 => $this->getRow(array_pop($names), array_pop($seens), $i)
        ];

        if (1 === $i) {
            return $this->getSingleName(
                $list[1]->name, $list[1]->seen, $hasDraft
            );
        }

        $this->buildNamesList($list, $i, $names, $seens);
        $this->cleanupNamesList($list, $i, $hasDraft);

        // Finally, we need to combine the names in the most
        // space efficient way possible
        if (1 === count(array_filter(array_column($list, 'name')))) {
            return $this->getSingleName(
                $list[1]->name, $list[1]->seen, $list[1]->draft
            );
        }

        return $this->getMultipleNames($list);
    }

    private function getRow(
        string $name = null,
        int $seen = null,
        int $index = 1,
        int $draft = 0
    ) {
        $short = trim(current(explode(' ', $name ?: '')), ' "');

        return (object) [
            'index' => $index,
            'name' => $name ?: '',
            'seen' => 1 === $seen,
            'draft' => 1 === $draft,
            'short' => current(explode('@', $short))
        ];
    }

    private function getEmptyRow()
    {
        return $this->getRow('', 0, 0);
    }

    /**
     * Iterate backwards through the list of names on all
     * of the messages and build an intelligent array of up
     * to three names.
     */
    private function buildNamesList(array &$list, int &$i, array $names, array $seens)
    {
        $prevName = null;
        $allRead = array_sum($seens) === count($seens);

        while ($names) {
            $lastName = array_pop($names);
            $lastSeen = (int) array_pop($seens);

            // We only decrement the distance counter when the name in
            // the message thread changes
            if (! $prevName || $prevName !== $lastName) {
                --$i;
            }

            $prevName = $lastName;

            // Try not to show the author at the beginning and end, but
            // only if the message is seen already or the current message
            // is unread.
            if ($list[3]->name === $list[1]->name
                && (1 === $list[3]->seen || 1 !== $lastSeen)
            ) {
                $list[3] = $this->getRow($lastName, $lastSeen, $i);
                continue;
            }

            // If the message is unread or if ALL messages are read,
            if (1 !== $lastSeen || $allRead) {
                // and if we have something in the middle,
                // and if the final message is read,
                // and finally if the middle message is unread,
                if ($list[2] && 1 === $list[3]->seen && 1 !== $list[2]->seen) {
                    // then move the middle message to the end.
                    $list[3] = $list[2];
                }

                // Middle message becomes most oldest unread message
                // in the chain, but only if there's nothing in the
                // middle.
                if (! $list[2]) {
                    if ($lastName !== $list[3]->name) {
                        $list[2] = $this->getRow($lastName, $lastSeen, $i);
                    }
                }
            }
        }
    }

    /**
     * Performs list compaction and cleanup.
     */
    private function cleanupNamesList(array &$list, int $count, bool $hasDraft = false)
    {
        // If this message has a draft in it, overwrite the last
        // entry with a Draft label
        if (true === $hasDraft) {
            $lastIndex = ($list[3]->index ?? 0)
                ? 3
                : (($list[2]->index ?? 0) ? 2 : 1);
            $list[$lastIndex] = $this->getRow(
                'Draft',
                1,
                $list[$lastIndex]->index,
                1
            );
        }

        // Clean up instances of the same name adjacent to itself
        if ($list[2] && $list[1]->name === $list[2]->name) {
            if ($list[1]->seen && $list[2]->seen) {
                $list[2] = $this->getEmptyRow();
            } elseif (! $list[1]->seen && $list[2]->seen) {
                $list[1] = $list[2];
                $list[2] = $this->getEmptyRow();
            }
        }

        // If nothing is in the middle and the first and third names
        // are the same,
        if (! $list[2] && $list[1]->name === $list[3]->name) {
            // and if both messages are seen or both unseen,
            // then kill the final one;
            if (($list[1]->seen && $list[3]->seen)
                || (! $list[1]->seen && ! $list[3]->seen)
            ) {
                $list[3] = $this->getEmptyRow();
            } elseif (! $list[1]->seen && $list[3]->seen) {
                // or if the last one is seen but the first is not,
                // preserve the unseen nature of the thread, but
                // collapse the two
                $list[1] = $list[3];
                $list[1]->seen = false;
                $list[3] = $this->getEmptyRow();
            }
        }

        if (! $list[2]) {
            $list[2] = $this->getEmptyRow();
        }

        if (! $list[3]) {
            $list[3] = $this->getEmptyRow();
        }
    }

    /**
     * Returns a string of more than one names.
     *
     * @return string
     */
    private function getMultipleNames(array $list)
    {
        $names = [
            1 => $list[1]->short,
            2 => $list[2]->short,
            3 => $list[3]->short
        ];

        // If all three names are under the limit, then use them all
        if (strlen($names[1]) + strlen($names[2]) + strlen($names[3]) <= self::MAX_LEN) {
            $prevItem = $list[1];
            $return = $this->getSingleName($names[1], $list[1]->seen, $list[1]->draft);

            if ($list[2]->index) {
                $prevItem = $list[2];
                $return .= $this->getDelimeter($list[1], $list[2])
                    .$this->getSingleName($names[2], $list[2]->seen, $list[2]->draft);
            }

            if ($list[3]->index) {
                $return .= $this->getDelimeter($prevItem, $list[3])
                    .$this->getSingleName($names[3], $list[3]->seen, $list[3]->draft);
            }

            return $return;
        }

        // Otherwise, try to see if the bookends are under the limit
        if (strlen($names[1]) + strlen($names[3]) <= self::MAX_LEN) {
            return $this->getSingleName($names[1], $list[1]->seen, $list[1]->draft)
                .'&nbsp;..&nbsp;'
                .$this->getSingleName($names[3], $list[3]->seen, $list[3]->draft);
        }

        // If not even the last name is short enough, just truncate it
        if (self::MAX_LEN - strlen($names[3]) <= 0) {
            return $this->getSingleName(
                substr($names[3], 0, self::MAX_LEN - 1).'.',
                $list[3]->seen,
                $list[3]->draft
            );
        }

        // Shorten the first if it's longer and try that
        if (strlen($names[1]) > strlen($names[3])) {
            $trimmed = substr($names[1], 0, self::MAX_LEN - strlen($names[3]));

            return $this->getSingleName($trimmed, $list[1]->seen, $list[1]->draft)
                .'&nbsp;..&nbsp;'
                .$this->getSingleName($names[3], $list[3]->seen, $list[3]->draft);
        }

        // Shorten the last one and send it back
        $trimmed = substr($names[3], 0, self::MAX_LEN - strlen($names[1]));

        return $this->getSingleName($names[1], $list[1]->seen, $list[1]->draft)
            .'&nbsp;..&nbsp;'
            .$this->getSingleName($trimmed, $list[3]->seen, $list[3]->draft);
    }

    private function getSingleName(string $name, bool $seen, bool $draft)
    {
        if (! $name) {
            return '';
        }

        if (true === $draft) {
            return sprintf('<var>%s</var>', $this->clean($name));
        } elseif (true === $seen) {
            return sprintf('<span>%s</span>', $this->clean($name));
        }

        return sprintf('<strong>%s</strong>', $this->clean($name));
    }

    private function getDelimeter(stdClass $itemA, stdClass $itemB)
    {
        if (! $itemA->index || ! $itemB->index) {
            return '';
        }

        return $itemB->index - $itemA->index > 1
            ? '&nbsp;..&nbsp;'
            : ',&nbsp;';
    }

    /**
     * Sanitizes and prints a value.
     */
    private function clean(string $value)
    {
        return htmlspecialchars($value, ENT_QUOTES, self::UTF8);
    }
}

<?php

namespace App\Messages;

class Names
{
    const MAX_LEN = 20;
    const UTF8 = 'utf-8';

    /**
     * Parses a list of names in the "from" header and their
     * corresponding "seen" flags, and returns an HTML formatted
     * string. The $names list is an array of all from headers in
     * order on the thread, and the $seens list is the corresponding
     * seen flag for each message.
     *
     * @param array $names
     * @param array $seens
     *
     * @return string
     */
    public function get(array $names, array $seens)
    {
        // First, remove any duplicate names that we don't want
        $names = $this->compress($names);

        return $this->getNames($names, $seens);
    }

    /**
     * Compresses the list of names and removes the duplicates.
     * If the same name appears later in the thread, then we don't
     * need any more than the first reference.
     *
     * @param array $names
     *
     * @return array
     */
    private function compress(array $names)
    {
        return $names;
    }

    /**
     * Prepare the name strings for the message.
     *
     * @param array $names List of all names on the message
     * @param array $seens List of seen flags for all names
     *
     * @return string
     *
     * @todo Move this to App\Messages\Names
     */
    private function getNames(array $names, array $seens)
    {
        $prevName = null;
        $i = count($names);
        $getRow = function ($name, $seen, $index) {
            $short = trim(current(explode(' ', $name)), ' "');

            return (object) [
                'name' => $name,
                'index' => $index,
                'seen' => 1 == $seen,
                'short' => current(explode('@', $short))
            ];
        };
        // Final set will have at most three, and at least two
        $final = [
            1 => $getRow(array_shift($names), array_shift($seens), 1),
            2 => null,
            3 => $getRow(array_pop($names), array_pop($seens), $i)
        ];

        if (1 === $i) {
            return sprintf(
                '<%s>%s</%s>',
                $final[1]->seen ? 'span' : 'strong',
                $this->clean($final[1]->name),
                $final[1]->seen ? 'span' : 'strong');
        }

        while ($names) {
            $lastName = array_pop($names);
            $lastSeen = array_pop($seens);

            if (! $prevName || $prevName != $lastName) {
                --$i;
            }

            $prevName = $lastName;

            // Don't show author twice, even if author is most recent,
            // but only if the final message has been seen
            if ($final[3]->seen
                && $final[3]->name == $final[1]->name)
            {
                $final[3] = $getRow($lastName, $lastSeen, $i);
            }
            // If the message is unread
            elseif (1 != $lastSeen) {
                // and if we have something in the middle
                if ($final[2]
                    // and if the final message is read
                    && $final[3]->seen
                    // and finally if the middle message is unread
                    && ! $final[2]->seen)
                {
                    // Move the middle message to the end
                    $final[3] = $final[2];
                }

                // Middle message becomes most oldest unread message
                // in the chain, but only if there's nothing in the
                // middle or the name is different.
                if (! $final[2]
                    || ($final[2]
                        && $final[2]->name != $lastName))
                {
                    if ($lastName !== $final[3]->name) {
                        $final[2] = $getRow($lastName, $lastSeen, $i);
                    }
                }
            }
        }

        // Clean up instances of the same name adjacent to itself
        if ($final[2] && $final[1]->name == $final[2]->name) {
            if ($final[1]->seen && $final[2]->seen) {
                unset($final[2]);
            }
            elseif (! $final[1]->seen && $final[2]->seen) {
                $final[1] = $final[2];
                unset($final[2]);
            }
        }

        if (! $final[2] && $final[1]->name == $final[3]->name) {
            if ($final[1]->seen && $final[3]->seen) {
                unset($final[3]);
            }
            elseif (! $final[1]->seen && $final[3]->seen) {
                $final[1] = $final[3];
                unset($final[3]);
            }
        }

        $i = 0;
        $raw = '';
        $return = '';
        $final = array_filter($final);

        // Finally, we need to combine the names in the most
        // space efficient way possible
        if (1 === count($final)) {
            return sprintf(
                '<%s>%s</%s>',
                $final[1]->seen ? 'span' : 'strong',
                $this->clean($final[1]->name),
                $final[1]->seen ? 'span' : 'strong');
        }

        foreach ($final as $item) {
            if (! $return) {
                $i = $item->index;
                $return = sprintf(
                    '<%s>%s</%s>',
                    $item->seen ? 'span' : 'strong',
                    $this->clean($item->short),
                    $item->seen ? 'span' : 'strong');
                continue;
            }

            $return .= ($item->index - $i > 1)
                ? '&nbsp;..&nbsp;'
                : ',&nbsp;';

            if (strlen($raw.$item->short) > self::MAX_LEN) {
                $return .= sprintf(
                    '<%s>%s</%s>',
                    $item->seen ? 'span' : 'strong',
                    $this->clean(
                        substr(
                            $item->short,
                            0,
                            self::MAX_LEN - strlen($raw)
                        )),
                    $item->seen ? 'span' : 'strong');

                return $return;
            }
            else {
                $raw .= $item->short;
                $return .= sprintf(
                    '<%s>%s</%s>',
                    $item->seen ? 'span' : 'strong',
                    $this->clean($item->short),
                    $item->seen ? 'span' : 'strong');
            }
        }

        return $return;
    }

    /**
     * Sanitizes and prints a value.
     *
     * @param string $value
     */
    private function clean($value)
    {
        return htmlspecialchars($value, ENT_QUOTES, self::UTF8);
    }
}

<?php

namespace SilverStripe\Cow\Utility;

interface FilterInterface
{
    /**
     * Filters an input array and returns the filtered results
     *
     * @param array $input
     * @return array
     */
    public function filter(array $input);
}

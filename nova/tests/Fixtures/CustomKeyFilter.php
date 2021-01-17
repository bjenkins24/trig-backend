<?php

namespace Laravel\Nova\Tests\Fixtures;

use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;

class CustomKeyFilter extends Filter
{
    /**
     * Get the key for the filter.
     *
     * @return string
     */
    public function key()
    {
        return 'custom_key';
    }

    /**
     * Apply the filter to the given query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed                                 $value
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Request $request, $query, $value)
    {
        return $query->whereKey($value);
    }

    /**
     * Get the filter's available options.
     *
     * @return array
     */
    public function options(Request $request)
    {
        return [];
    }
}

<?php

function implodeWithAnd(array $items) {
    $count = count($items);
    if ($count === 0) return '';
    if ($count === 1) return ucfirst($items[0]);
    if ($count === 2) return ucfirst(implode(' and ', $items));
    $last = array_pop($items);
    return ucfirst(implode(', ', $items)) . ' and ' . $last;
}


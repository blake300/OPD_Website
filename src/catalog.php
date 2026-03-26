<?php

declare(strict_types=1);

function opd_product_categories(): array
{
    return [
        'AutoBailer Artifical Lift',
        'Parts',
        'Tools',
        'Services',
        'Supplies',
        'Used Equipment',
        'Hidden',
        'Hidden A',
        'Hidden B',
        'Hidden C',
    ];
}

function opd_hidden_categories(): array
{
    return ['Hidden', 'Hidden A', 'Hidden B', 'Hidden C'];
}

function opd_public_product_categories(): array
{
    $hidden = opd_hidden_categories();
    return array_values(array_filter(
        opd_product_categories(),
        fn($cat) => !in_array($cat, $hidden, true)
    ));
}

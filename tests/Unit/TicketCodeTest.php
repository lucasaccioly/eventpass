<?php

use App\Support\TicketCode;

it('generates unambiguous, unique 10-char codes', function () {
    $codes = collect(range(1, 1000))->map(fn () => TicketCode::generate());

    $codes->each(fn (string $code) => expect($code)->toMatch('/^[2-9A-HJ-NP-Z]{10}$/'));
    expect($codes->unique())->toHaveCount(1000);
});

it('normalizes typed codes', function () {
    expect(TicketCode::normalize('  ab3k-9xyz2m '))->toBe('AB3K9XYZ2M');
});

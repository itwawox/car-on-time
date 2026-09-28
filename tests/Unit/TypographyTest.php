<?php

use App\Support\Typography;

// Короткие предлоги и союзы в заголовках переносятся вместе со следующим словом

it('glues short prepositions to the next word', function () {
    expect(Typography::nbsp('Аренда авто в Крыму без предоплаты'))
        ->toBe("Аренда авто в\u{00A0}Крыму без\u{00A0}предоплаты");
});

it('handles several short words in a row and the start of a line', function () {
    expect(Typography::nbsp('В горы и в город'))->toBe("В\u{00A0}горы и\u{00A0}в\u{00A0}город");
});

it('leaves longer words alone', function () {
    expect(Typography::nbsp('Машины для семьи'))->toBe("Машины для\u{00A0}семьи")
        ->and(Typography::nbsp('Внедорожники'))->toBe('Внедорожники');
});

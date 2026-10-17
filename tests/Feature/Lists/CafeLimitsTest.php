<?php

use App\Lists\CafeLimits;
use App\Lists\ListEditor;
use App\Lists\ListName;
use App\Models\Filter\FilterAreaRule;
use App\Models\User;

beforeEach(function () {
    $this->signIn(User::factory()->admin()->withTwoFactor()->create());
    $editor = app(ListEditor::class);
    $editor->save(ListName::NetCafeIps, "5.5.5.5\n6.6.6.6", $editor->fingerprint(ListName::NetCafeIps));
});

it('defaults every limit to 0', function () {
    expect(app(CafeLimits::class)->get('5.5.5.5'))->toBe(array_fill_keys(CafeLimits::FIELDS, 0));
});

it('stores the limits as a cafe_limit area rule', function () {
    app(CafeLimits::class)->set('5.5.5.5', ['login_ip' => 20, 'login_pc' => 15, 'fw_ip' => -1]);
    app(CafeLimits::class)->set('5.5.5.5', ['login_ip' => 25, 'login_pc' => 15, 'fw_ip' => -1]);

    $rule = FilterAreaRule::query()->sole();
    expect($rule->rule)->toBe('cafe_limit')
        ->and($rule->area)->toBe('-1')
        ->and($rule->target)->toBe('5.5.5.5')
        ->and($rule->params)->toBe(['login_ip' => 25, 'login_pc' => 15, 'fw_ip' => -1, 'fw_pc' => 0, 'job_ip' => 0, 'job_pc' => 0, 'fgw_ip' => 0, 'fgw_pc' => 0]);
});

it('refuses values outside -1 to 1000 and IPs that are not cafes', function (string $ip, array $limits) {
    expect(fn () => app(CafeLimits::class)->set($ip, $limits))->toThrow(InvalidArgumentException::class);
    expect(FilterAreaRule::count())->toBe(0);
})->with([
    ['5.5.5.5', ['login_ip' => -2]],
    ['5.5.5.5', ['fgw_pc' => 1001]],
    ['7.7.7.7', ['login_ip' => 5]],
]);

it('removes the limits together with the cafe IP', function () {
    app(CafeLimits::class)->set('5.5.5.5', ['login_ip' => 20]);
    app(CafeLimits::class)->set('6.6.6.6', ['login_ip' => 30]);
    $editor = app(ListEditor::class);

    $editor->save(ListName::NetCafeIps, '6.6.6.6', $editor->fingerprint(ListName::NetCafeIps));

    expect(FilterAreaRule::query()->pluck('target')->all())->toBe(['6.6.6.6']);
});

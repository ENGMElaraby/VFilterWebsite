<?php

it('lets everyone reach the sign-in page when the allow-list is empty', function () {
    config(['vfilter.allowed_ips' => []]);

    $this->get('/admin/login', ['REMOTE_ADDR' => '203.0.113.9'])->assertOk();
});

it('refuses ips outside the allow-list on every panel page', function (string $path) {
    config(['vfilter.allowed_ips' => ['10.0.0.1', '192.168.1.0/24']]);

    $this->call('GET', $path, server: ['REMOTE_ADDR' => '203.0.113.9'])->assertForbidden();
})->with(['/admin/login', '/admin']);

it('lets listed ips and cidr ranges through', function (string $ip) {
    config(['vfilter.allowed_ips' => ['10.0.0.1', '192.168.1.0/24', '2001:db8::/32']]);

    $this->call('GET', '/admin/login', server: ['REMOTE_ADDR' => $ip])->assertOk();
})->with(['10.0.0.1', '192.168.1.77', '2001:db8::5']);

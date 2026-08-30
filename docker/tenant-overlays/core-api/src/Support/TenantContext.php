<?php

namespace Fleetbase\Support;

final class TenantContext
{
    private static ?string $companyUuid = null;

    private static bool $required = false;

    public static function activate(string $companyUuid): void
    {
        $companyUuid = trim($companyUuid);
        if ($companyUuid === '') {
            throw new \InvalidArgumentException('Tenant company UUID cannot be empty.');
        }

        static::$companyUuid = $companyUuid;
        static::$required    = true;
    }

    public static function enforce(?string $companyUuid = null): void
    {
        if ($companyUuid !== null) {
            static::activate($companyUuid);

            return;
        }

        static::$companyUuid = null;
        static::$required    = true;
    }

    public static function companyUuid(): ?string
    {
        return static::$companyUuid;
    }

    public static function isRequired(): bool
    {
        return static::$required;
    }

    public static function clear(): void
    {
        static::$companyUuid = null;
        static::$required    = false;
    }

    public static function run(string $companyUuid, callable $callback, bool $syncSession = true): mixed
    {
        $previousCompanyUuid    = static::$companyUuid;
        $previousRequired       = static::$required;
        $session                = null;
        $hadSessionCompany      = false;
        $previousSessionCompany = null;

        if ($syncSession && function_exists('app') && app()->bound('session')) {
            $session                = app('session');
            $hadSessionCompany      = $session->has('company');
            $previousSessionCompany = $session->get('company');
        }

        static::activate($companyUuid);

        if ($session !== null) {
            $session->put('company', $companyUuid);
        }

        try {
            return $callback();
        } finally {
            static::$companyUuid = $previousCompanyUuid;
            static::$required    = $previousRequired;

            if ($session !== null) {
                if ($hadSessionCompany) {
                    $session->put('company', $previousSessionCompany);
                } else {
                    $session->forget('company');
                }
            }
        }
    }
}

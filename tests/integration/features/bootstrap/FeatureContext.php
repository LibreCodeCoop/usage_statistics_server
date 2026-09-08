<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\BeforeSuite;
use Behat\Step\Given;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Libresign\NextcloudBehat\NextcloudApiContext;

final class FeatureContext extends NextcloudApiContext {
    #[BeforeSuite]
    public static function beforeSuite(BeforeSuiteScope $scope): void {
        parent::beforeSuite($scope);
        self::runCommand('config:system:set debug --value true --type boolean');
        self::runCommand('config:system:set auth.bruteforce.protection.enabled --value false --type boolean');
        self::runCommand('config:system:set ratelimit.protection.enabled --value false --type boolean');
        self::runCommand('app:enable --force usage_statistics_server');
    }

    #[Given('as anonymous user')]
    public function asAnonymousUser(): void {
        $this->setCurrentUser('');
    }

    #[Given('the output of the last command should contain the following text:')]
    public static function theOutputOfTheLastCommandContains(PyStringNode $text): void {
        $expected = (string) $text;
        if (!str_contains(self::$commandOutput, $expected)) {
            throw new RuntimeException(
                'The output of the last command does not contain: ' . $expected . "\nActual output:\n" . self::$commandOutput,
            );
        }
    }
}

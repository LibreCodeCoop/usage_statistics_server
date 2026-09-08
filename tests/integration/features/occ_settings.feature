# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: configure Usage Statistics Server with OCC

  Scenario: read and update the retention period
    Given run the command "usage-statistics-server:retention:get"
    Then the output of the last command should locally contain the following text:
      """
      Retention period: 1095 days
      """
    When run the command "usage-statistics-server:retention:set 120"
    Then the output of the last command should locally contain the following text:
      """
      Retention period set to 120 days
      """
    When run the command "usage-statistics-server:retention:get"
    Then the output of the last command should locally contain the following text:
      """
      Retention period: 120 days
      """
    When run the command "usage-statistics-server:retention:set 44" with result code 2
    Then the output of the last command should locally contain the following text:
      """
      retentionDays must be between 45 and 3650.
      """
    When run the command "usage-statistics-server:retention:set 1095"
    Then the output of the last command should locally contain the following text:
      """
      Retention period set to 1095 days
      """

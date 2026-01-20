# Task 003: PHPCS Configuration with Slevomat

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the PHPCS (PHP CodeSniffer) configuration with Slevomat coding standard rules. This validates and auto-fixes coding standard violations that php-cs-fixer doesn't handle.

## Context
- Location: `/phpcs.xml` (project root)
- Uses Slevomat Coding Standard for additional rules
- Directories to scan: `packages/`, `demo/app/`, `demo/modules/`

## Requirements (Test Descriptions)
- [ ] `it has valid phpcs.xml configuration`
- [ ] `it includes Slevomat coding standard rules`
- [ ] `it enforces multiline function signatures when function has parameters`
- [ ] `it enforces trailing commas in multiline function declarations`
- [ ] `it disallows useless parentheses including around new keyword`
- [ ] `it requires blank line before return statements`
- [ ] `it scans packages directory`
- [ ] `it scans demo/app directory`
- [ ] `it scans demo/modules directory`
- [ ] `phpcs runs without configuration errors`

## Acceptance Criteria
- All requirements have passing tests
- phpcs validates the configuration file
- Rules enforce consistent coding standards

## Files to Create
```
phpcs.xml
```

## XML Configuration
```xml
<?xml version="1.0"?>
<ruleset name="Marko Framework Coding Standard">
    <description>Marko Framework Coding Standard</description>

    <file>packages</file>
    <file>demo/app</file>
    <file>demo/modules</file>

    <arg name="colors"/>
    <arg value="sp"/>

    <!-- Require multiline method signatures -->
    <rule ref="SlevomatCodingStandard.Functions.RequireMultiLineCall">
        <properties>
            <property name="minLineLength" value="1"/>
        </properties>
    </rule>

    <!-- Require trailing commas in multiline -->
    <rule ref="SlevomatCodingStandard.Functions.RequireTrailingCommaInDeclaration"/>

    <!-- Disallow useless parentheses -->
    <rule ref="SlevomatCodingStandard.PHP.UselessParentheses">
        <properties>
            <property name="ignoreComplexTernaryConditions" value="true"/>
        </properties>
    </rule>

    <!-- Require blank line before return -->
    <rule ref="SlevomatCodingStandard.ControlStructures.JumpStatementsSpacing">
        <properties>
            <property name="linesCountBeforeControlStructure" value="1"/>
            <property name="tokensToCheck" type="array">
                <element value="T_RETURN"/>
            </property>
        </properties>
    </rule>
</ruleset>
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)

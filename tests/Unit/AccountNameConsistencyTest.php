<?php

namespace Tests\Unit;

use App\Support\AccountName;
use PHPUnit\Framework\TestCase;

class AccountNameConsistencyTest extends TestCase
{
    /**
     * @dataProvider consistentNames
     */
    public function test_form_name_is_consistent_with_database_name(string $formName, string $databaseName): void
    {
        $this->assertTrue(AccountName::from($formName)->isConsistentWith($databaseName));
    }

    /**
     * @dataProvider inconsistentNames
     */
    public function test_form_name_is_not_consistent_with_a_different_account_name(string $formName, string $databaseName): void
    {
        $this->assertFalse(AccountName::from($formName)->isConsistentWith($databaseName));
    }

    public static function consistentNames(): array
    {
        return [
            'exact' => ['DELA CRUZ, JUAN', 'DELA CRUZ, JUAN'],
            'case and spacing' => ['  dela   cruz,   juan ', 'DELA CRUZ, JUAN'],
            'punctuation' => ['Dela Cruz, Juan', 'DELA CRUZ, JUAN'],
            'first last order' => ['Juan Dela Cruz', 'DELA CRUZ, JUAN'],
            'middle name omitted' => ['Juan Dela Cruz', 'DELA CRUZ, JUAN PEDRO'],
            'middle initial omitted' => ['Juan Dela Cruz', 'DELA CRUZ, JUAN P.'],
            'middle initial expanded' => ['Juan P. Dela Cruz', 'DELA CRUZ, JUAN PEDRO'],
            'stored initial kept' => ['Juan Dela Cruz', 'DELA CRUZ, JUAN D.'],
            'suffix omitted' => ['Catherine Abayari', 'ABAYARI II, CATHERINE S.'],
            'matching suffix' => ['Catherine Abayari II', 'ABAYARI II, CATHERINE'],
            'jr omitted' => ['Albert Basilio', 'BASILIO, ALBERT JR S.'],
            'de la joined' => ['Juan De La Cruz', 'DELA CRUZ, JUAN'],
            'de leon joined' => ['Juan Deleon', 'DE LEON, JUAN'],
            'de leon split' => ['Juan De Leon', 'DELEON, JUAN'],
            'sta abbreviation' => ['Juan Sta. Cruz', 'STA. CRUZ, JUAN'],
            'n-tilde' => ['Juan Munoz', 'MUÑOZ, JUAN'],
            'hyphen' => ['Dila Dila Barangay Hall', 'DILA-DILA BARANGAY HALL'],
            'organization order' => ['Chapel Holy Family', 'HOLY FAMILY CHAPEL'],
            'single word' => ['AETAHANAN', 'AETAHANAN'],
            'no comma person' => ['Gregorio Bejago', 'BEJAGO JR. GREGORIO D'],
            'generation i omitted' => ['Juan Delos Santos', 'DELOS SANTOS I, JUAN'],
            'surname only' => ['Dela Cruz', 'DELA CRUZ, JUAN'],
            'surname only joined particle' => ['De La Cruz', 'DELA CRUZ, JUAN'],
            'surname only compound' => ['De Leon', 'DE LEON, JUAN'],
            'surname only with stored suffix' => ['Abayari', 'ABAYARI II, CATHERINE S.'],
            'surname only with stored middle name' => ['Dela Cruz', 'DELA CRUZ, JUAN PEDRO'],
        ];
    }

    public static function inconsistentNames(): array
    {
        return [
            'blank form' => ['', 'DELA CRUZ, JUAN'],
            'blank record' => ['Juan Dela Cruz', ''],
            'different person' => ['Maria Santos', 'DELA CRUZ, JUAN'],
            'first name only' => ['Juan', 'DELA CRUZ, JUAN'],
            'middle name instead of first name' => ['Pedro Dela Cruz', 'DELA CRUZ, JUAN PEDRO'],
            'missing surname particle' => ['Juan Cruz', 'DELA CRUZ, JUAN'],
            'extra surname word' => ['Juan Dela Cruz Santos', 'DELA CRUZ, JUAN'],
            'wrong suffix' => ['Catherine Abayari III', 'ABAYARI II, CATHERINE'],
            'suffix not on record' => ['Juan Cruz Jr', 'CRUZ, JUAN'],
            'wrong generation i' => ['Juan Delos Santos II', 'DELOS SANTOS I, JUAN'],
            'initial instead of first name' => ['J. Dela Cruz', 'DELA CRUZ, JUAN'],
            'similar given name' => ['Juana Cruz', 'CRUZ, JUAN'],
            'organization missing a word' => ['Holy Family', 'HOLY FAMILY CHAPEL'],
            'single word mismatch' => ['AETAHANAN', 'BEJAGO'],
        ];
    }
}

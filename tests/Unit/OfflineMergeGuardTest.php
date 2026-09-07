<?php

namespace Tests\Unit;

use App\Models\Reading;
use App\Models\ReadingOffline;
use App\Services\OfflineMergeGuard;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class OfflineMergeGuardTest extends TestCase
{
    private OfflineMergeGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new OfflineMergeGuard();
    }

    public function test_pick_winner_keeps_highest_present_reading(): void
    {
        $olderCopy = $this->offline(1, 3784, 3807);
        $newer = $this->offline(2, 3807, 3823);

        $winner = $this->guard->pickWinner(new Collection([$olderCopy, $newer]));

        $this->assertSame(2, $winner->id);
        $this->assertSame(3823, (int) $winner->present_reading);
    }

    public function test_pick_winner_breaks_ties_with_latest_id(): void
    {
        $first = $this->offline(10, 100, 120);
        $second = $this->offline(20, 100, 120);

        $winner = $this->guard->pickWinner(new Collection([$first, $second]));

        $this->assertSame(20, $winner->id);
    }

    public function test_same_meter_state_matches_previous_and_present(): void
    {
        $reading = new Reading([
            'previous_reading' => '3784',
            'present_reading' => '3807',
        ]);
        $off = $this->offline(1, 3784, 3807);

        $this->assertTrue($this->guard->isSameMeterState($reading, $off));

        $off->present_reading = 3823;
        $this->assertFalse($this->guard->isSameMeterState($reading, $off));
    }

    private function offline(int $id, int $previous, int $present): ReadingOffline
    {
        $row = new ReadingOffline();
        $row->id = $id;
        $row->previous_reading = $previous;
        $row->present_reading = $present;
        $row->reference_no = 'NST-TEST-'.$id;

        return $row;
    }
}

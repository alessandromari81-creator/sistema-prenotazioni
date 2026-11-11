<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use PHPUnit\Framework\TestCase;

/** La Policy è una classe semplice: si prova senza database, con modelli creati a mano. */
class BookingPolicyTest extends TestCase
{
    private function user(int $id, string $role = 'user'): User
    {
        return (new User)->forceFill(['id' => $id, 'role' => $role]);
    }

    public function test_owner_can_cancel_own_booking(): void
    {
        $booking = new Booking(['user_id' => 1]);

        $this->assertTrue((new BookingPolicy)->cancel($this->user(1), $booking));
    }

    public function test_another_user_cannot_cancel_it(): void
    {
        $booking = new Booking(['user_id' => 1]);

        $this->assertFalse((new BookingPolicy)->cancel($this->user(2), $booking));
    }

    public function test_admin_can_cancel_any_booking(): void
    {
        $booking = new Booking(['user_id' => 1]);

        $this->assertTrue((new BookingPolicy)->cancel($this->user(99, 'admin'), $booking));
    }
}

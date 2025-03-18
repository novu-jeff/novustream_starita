<?php

namespace App\Services;

use App\Models\SupportTicket;


class SupportTicketService {

    public function getTickets($id = null) {
        if(is_null($id)) {
            return SupportTicket::with(['user', 'ticket_category'])->get();
        } else {
            return SupportTicket::with(['user', 'ticket_category'])
                ->where('user_id', $id)
                ->get();
        }
    }

    public function getTicketByID($id) {
        return SupportTicket::with(['user', 'ticket_category'])
            ->where('id', $id)->first();
    }

    public function getOpenTickets() {
        return SupportTicket::with(['user', 'ticket_category'])
            ->where('status', 'open')
            ->get();
    }

    public function getCloseTickets() {
        return SupportTicket::with(['user', 'ticket_category'])
            ->where('status', 'close')
            ->get();
    }

    public function remove(int $id) {
        $ticket = SupportTicket::where('id', $id);
        if($ticket->exists()) {
            $data = $ticket->first();
            if($ticket->delete()) {
                return [
                    'status' => 'success',
                    'message' => 'Ticket id `' . $data->id . '` deleted successfully'
                ];
            }
        } else {
            return [
                'status' => 'error',
                'message' => 'Ticket id `'.$id.'` does not exists'
            ];
        }
    }
}
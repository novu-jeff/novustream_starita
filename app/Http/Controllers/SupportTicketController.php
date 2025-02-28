<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\TicketsCategory;
use App\Services\SupportTicketService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class SupportTicketController extends Controller {

    protected $supportTicketService;

    public function __construct(SupportTicketService $supportTicketService) {
        $this->supportTicketService = $supportTicketService;
    }

    public function index(Request $request) {
        $data = $this->supportTicketService->getTickets();
        return $request->ajax() ? $this->datatable($data) : view('support-ticket.index');
    }

    public function show(int $id, Request $request) {
        return $request->wantsJson() 
            ? ['status' => 'success', 'data' => $this->supportTicketService->getTicketByID($id)] 
            : view('support-ticket.edit');
    }

    public function create(Request $request) {
        $user = Auth::user();
        $data = $user->user_type == 'client' ? $this->supportTicketService->getTickets($user->id) : $this->supportTicketService->getTickets();;
        return $request->ajax() 
            ? $this->datatable($data) 
            : view('support-ticket.create', ['data' => $data, 'categories' => TicketsCategory::all()]);
    }

    public function store(Request $request) {

        try {

            $rules = [
                'category' => 'required|exists:tickets_category,id',
                'message' => 'required'
            ];

            $payload = $request->all();
    
            $validator = Validator::make($payload, $rules);
    
            if($validator->fails()) {
                return redirect()->back()->withInput()->with('alert', [
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                ]);
            }
    
            $ticket = SupportTicket::where('user_id', Auth::user()->id)
                ->where('status', 'open');
    
            if($ticket->count() >= 3) {

                return redirect()->back()->withInput()->with('alert', [
                    'status' => 'error',
                    'message' => 'Unable to proceed as we noticed you still have ' . $ticket->count() . ' open tickets.',
                ]);
              
            }
    
            SupportTicket::create([
                'user_id' => Auth::user()->id,
                'ticket_no' => $this->generate_code('TICKET'),
                'status' => 'open',
                'prioritization' => 0,
                'category_id' => $payload['category'], 
                'message' => $payload['message'],
                'feedback' => null,
                'assisted_by' => null,
            ]);
    
            return redirect()->back()->with('alert', [
                'status' => 'success',
                'message' => 'Ticket created successfully',
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->with('alert', [
                'status' => 'success',
                'message' => 'Error occured: '. $e->getMessage(),
            ]);
        }
    }

    public function edit(int $id) {
        $data = $this->supportTicketService->getTicketByID($id);
        return $data ? view('support-ticket.edit', compact('data')) : redirect()->route('support-ticket.index');
    }

    public function update(Request $request, $id) {

        try {
            
            $is_exists = SupportTicket::where('id', $id)->exists();

            if($is_exists) {

                $rules = [
                    'feedback' => 'required'
                ];

                $payload = $request->all();

                $validator = Validator::make($payload, $rules);

                if($validator->fails()) {
                    return redirect()->back()->with('alert', [
                        'status' => 'error',
                        'message' => $validator->errors()->first()
                    ]);
                }

                $data = [
                    'status' => 'close',
                    'feedback' => $payload['feedback'],
                    'assisted_by' => Auth::user()->id,
                ];

                SupportTicket::where('id', $id)
                    ->update($data);

                return redirect()->back()->with('alert', [
                    'status' => 'success',
                    'message' => 'Ticket closed successfully',
                ]);

            } else {
                return redirect()->back()->with('alert', [
                    'status' => 'error',
                    'message' => 'Ticket id `'.$id.'` does not exists'
                ]);
            }

        } catch (\Exception $e) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => 'Error occured: '. $e->getMessage(),
            ]);
        }
    }

    public function destroy($id) {
        DB::beginTransaction();
        try {
            $response = $this->supportTicketService->remove($id);
            DB::commit();
            return response($response);
        } catch (\Exception $e) {
            DB::rollBack();
            return response(['message' => $e->getMessage(), 'status' => 'delete failed'], 500);
        }
    }

    public function datatable($query)
    {
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('status', function ($row) {
                return $row->status == 'open'
                    ? '<div class="alert alert-danger px-3 py-2 text-uppercase text-center fw-bold mb-0">Open</div>'
                    : '<div class="alert alert-primary px-3 py-2 text-uppercase text-center fw-bold mb-0">Close</div>';
            })
            ->editColumn('created_at', function ($row) {
                return Carbon::parse($row->created_at)->format('F d, Y');
            })
            ->addColumn('actions', function ($row) {

                $viewBtn = '<button type="button" class="btn-view btn btn-primary fw-bold text-uppercase px-4 py-2 text-white view-btn" data-id="' . $row->id . '">View</button>';
                $deleteBtn = '<button type="button" class="btn-delete btn btn-danger fw-bold text-uppercase px-4 py-2 text-white remove-btn" data-id="' . $row->id . '">Delete</button>';
                $respondBtn = '<a href="'.route('support-ticket.edit', ['ticket' => $row->id]).'" class="text-uppercase px-4 py-2 fw-bold btn btn-success text-white text"" data-id="'.$row->id.'">Respond</a>';

                if (Auth::check()) { 
                    
                    return Auth::user()->user_type == 'client'
                        ? "<div class='d-flex gap-2'>{$viewBtn} {$deleteBtn}</div>"
                        : "<div class='d-flex gap-2'>{$viewBtn} {$respondBtn} {$deleteBtn}</div>";
                }

                return "<div class='d-flex gap-2'>{$viewBtn}</div>"; // Default case for unauthenticated users
            })
            ->rawColumns(['status', 'actions']) // Ensure both columns allow raw HTML output
            ->make(true);
    }
    
    
    private function generate_code($prefix) {

        $datePart = date('Ymds');
        $randomPart = mt_rand(1000, 9999);

        return $prefix . '-' . $datePart . '-' . $randomPart;
    }
}

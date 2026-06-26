<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile settings updated successfully.',
            'user' => $user
        ], 200);
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided current password does not match our records.'
            ], 422);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.'
        ], 200);
    }

    public function createSupportTicket(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'status' => 'open'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Support request submitted successfully. Ticket #' . $ticket->id,
            'ticket' => $ticket
        ], 201);
    }

    public function indexSupportTickets(Request $request)
    {
        $user = Auth::user();
        $tickets = SupportTicket::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'tickets' => $tickets
        ], 200);
    }

    public function indexAllSupportTickets(Request $request)
    {
        if (!$request->user()->can('view_support_tickets')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $query = SupportTicket::with('user');

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $paginate = $request->input('paginate');
        if ($paginate === 'true') {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            return response()->json($paginated);
        }

        $tickets = $query->orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'tickets' => $tickets
        ], 200);
    }

    public function updateSupportTicketStatus(Request $request, $id)
    {
        if (!$request->user()->can('manage_support_tickets')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed'
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $validated['status'];
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'Support ticket status updated successfully.',
            'ticket' => $ticket
        ], 200);
    }
}

<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Http\Controllers\SuperAdmin\Crm\ChatController as WebChatController;
use Illuminate\Http\Request;

class ChatController extends WebChatController
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Chats fetched successfully.',
            'data' => [
                'chats' => $this->chatList(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|integer|required_without:lead_id',
            'lead_id' => 'nullable|integer|required_without:employee_id',
        ]);

        $id = $this->openChat((int) $request->input('employee_id', 0), (int) $request->input('lead_id', 0));

        return response()->json([
            'success' => true,
            'message' => 'Chat ready.',
            'data' => $this->chatDetail($id),
        ]);
    }

    public function show(int $id)
    {
        $chat = $this->chatDetail($id);
        if (!$chat) {
            return response()->json([
                'success' => false,
                'message' => 'Chat not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat fetched successfully.',
            'data' => $chat,
        ]);
    }

    public function message(Request $request, int $id)
    {
        $request->validate(['message' => 'required|string|max:5000']);
        if (!$this->canSee($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Chat not found.',
                'data' => null,
            ], 404);
        }

        $this->addMessage($id, trim($request->input('message')));

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'data' => $this->chatDetail($id),
        ]);
    }
}

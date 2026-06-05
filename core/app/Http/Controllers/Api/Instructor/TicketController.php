<?php


namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Traits\SupportTicketManager;

class TicketController extends Controller
{
    use SupportTicketManager;

    public function __construct()
    {
        parent::__construct();
        $this->userType     = 'instructor';
        $this->column       = 'instructor_id';
        $this->user = auth()->user();
        $this->apiRequest = true;
    }
}

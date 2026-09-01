<?php

namespace App\Http\Controllers;

use App\Models\Frame;
use App\Models\Review;
use Illuminate\View\View;

class AboutController extends Controller
{
    /**
     * The shop's "who we are / how to reach us" page.
     *
     * The three figures in the story band are counted live rather than typed
     * into the copy, so the page cannot claim a catalogue the shop no longer
     * has. They are cheap count queries and the page is not hot.
     */
    public function index(): View
    {
        return view('about', [
            'frameCount' => Frame::where('is_active', true)->count(),
            'reviewCount' => Review::where('is_approved', true)->count(),
        ]);
    }
}

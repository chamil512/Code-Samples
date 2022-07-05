<?php

namespace Modules\Academic\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AcademicController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Factory|View
     */
    public function dashboard()
    {
        return view('academic::index');
    }
}

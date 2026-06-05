<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\Request;

class ManageGoalController extends Controller
{
    public function index(){

        $pageTitle = 'Manage Goals';
        $goals = Goal::paginate(getPaginate());
        return view('admin.goal.lists', compact('pageTitle', 'goals'));
        
    }

    public function save(Request $request, $id= 0){
        $request->validate([
            'title'     =>'required',
        ]);
        if($id){
            $goal = Goal::find($id);
        }else{
            $goal = new Goal();
        }
        $goal->title = $request->title;
        $goal->save();

        $notify[] =['success','Goal save successfully'];
        return back()->withNotify($notify);
     
     
    }

    public function status($id){
        return Goal::changeStatus($id);
    }
}

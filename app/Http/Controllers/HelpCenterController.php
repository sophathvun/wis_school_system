<?php

namespace App\Http\Controllers;

use App\Support\HelpCenter;
use Illuminate\Http\Request;

class HelpCenterController
{
    public function index(Request $request,HelpCenter $help)
    {
        $input=$request->validate(['q'=>'nullable|string|max:200','category'=>'nullable|in:getting-started,g9,g12,attendance,administrator,students']);
        $query=trim($input['q']??'');$category=$input['category']??'';
        $available=$help->articles($request->user());
        $articles=$help->search($available,$query,$category);
        $categories=array_filter(HelpCenter::CATEGORIES,fn($key)=>collect($available)->contains('category',$key),ARRAY_FILTER_USE_KEY);
        return response()->view('help.index',compact('articles','available','categories','query','category'))->header('Cache-Control','private, no-store');
    }

    public function show(Request $request,string $slug,HelpCenter $help)
    {
        $available=$help->articles($request->user());
        abort_unless(isset($available[$slug]),404);
        $article=$available[$slug];$html=$help->render($article['body']);
        $related=array_slice(array_filter($available,fn($item)=>$item['category']===$article['category']&&$item['slug']!==$slug),0,4);
        return response()->view('help.article',compact('article','html','related'))->header('Cache-Control','private, no-store');
    }
}

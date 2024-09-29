<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Tags\Tag;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // public function index(Request $request)
    // {
    //     $userId = Auth::user()->id;
    //     $products = Product::with(['media', 'user'])->get();
    //     if ($request->routeIs('products.index')) {
    //         return view('product', compact('products'));
    //     }elseif($request->routeIs('products.check')){
    //         return view('product-check', compact('products'));
    //     }
    // }
    public function index(Request $request)
    {
            if ($request->routeIs('products.index')) {
                $products = Product::with(['media', 'user'])
                ->where('status', 100)
                ->get();
                    return view('n_login.Product', compact('products'));
            }elseif($request->routeIs('products.check')){  
                $userId = Auth::user()->id;
                $userProducts = Product::with(['media', 'user'])
                ->where('user_id', $userId)
                ->get();
                if ($userProducts->isEmpty()) {
                    $message = '您目前沒有任何商品，趕緊刊登一個吧!';
                }else {
                    $message = null; // 如果有商品，則不顯示訊息
                };
                return view('login.Product-check', compact('userProducts', 'message'));
            }elseif ($request->routeIs('products.info')) {
                $products = Product::with(['media', 'user'])->get();
                return view('login.Product-info', compact('products'));
            }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $tags = Tag::all();

        return view('login.Product-create', compact('tags'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'digits_between:1,10'],
            'description' => ['required', 'string'],
            'grade' => ['required', 'string'],      
            'semester' => ['required', 'string'],   
            'category' => ['required', 'string'],   
            'image' => ['nullable', 'image'],       
        ]);

        $product = Product::create($validated + [
            'user_id' => auth()->id(),
        ]);

        // 添加圖片
        if ($request->hasFile('image')) {
            $product->addMedia($request->file('image'))->toMediaCollection('images');
        }

        // 獲取表單資料
        $gradeSlug = $request->input('grade');      
        $semesterSlug = $request->input('semester'); 
        $categorySlug = $request->input('category'); 

        // 根據年級查找對應的年級標籤
        $gradeTag = Tag::where('slug->zh', $gradeSlug)->where('type', '年級')->first();
        // 根據學期查找對應的學期標籤
        $semesterTag = Tag::where('slug->zh', $semesterSlug)->where('type', '學期')->first();
        // 根據課程類別查找對應的課程標籤
        $categoryTag = Tag::where('slug->zh', $categorySlug)->where('type', '課程')->first();

        // 附加年級標籤到產品
        if ($gradeTag) {
            $product->attachTag($gradeTag);
        }
        // 附加學期標籤到產品
        if ($semesterTag) {
            $product->attachTag($semesterTag);
        }
        // 附加課程標籤到產品
        if ($categoryTag) {
            $product->attachTag($categoryTag);
        }

        return redirect()->route('products.create')->with('success', '產品已成功創建並附加標籤');
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $tag = request('tag');

        // 根據標籤過濾商品
        $filteredProducts = $product->whereHas('tags', function ($query) use ($tag) {
            $query->where('name', $tag);
        })->get();

        // 返回過濾後的商品
        return view('products.show', compact('filteredProducts'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        if ($product->status == 100) {
            $newStatus = 200;
            $message = '商品已下架！';  // 當前狀態是 100（上架），切換為 200（下架）
        } else {
            $newStatus = 100;
            $message = '商品已上架！';  // 當前狀態是 200（下架），切換為 100（上架）
        }

        // 更新商品的狀態
        $product->update([
            'status' => $newStatus,         
        ]);

        // 返回更新成功的響應和相應的消息
        return response()->json([
            'message' => $message,
            'new_status' => $newStatus,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        // 清空產品的媒體集合
        $product->clearMediaCollection('images');

        // 刪除產品本身，這將自動處理標籤的分離
        $product->delete();

        // 重定向到產品列表頁面，並帶有成功消息
        return redirect()->route('products.index')->with('success', '產品及其關聯媒體和標籤已成功刪除');
    }
}

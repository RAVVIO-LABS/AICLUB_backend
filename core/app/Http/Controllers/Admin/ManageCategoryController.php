<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubCategory;
use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ManageCategoryController extends Controller
{
    public function index(){
        $pageTitle = 'Manage Categories';
        $categories = Category::searchable(['name'])->paginate(getPaginate());
        return view('admin.category.lists', compact('pageTitle', 'categories'));
    }


    public function save(Request $request, $id = null){
        $request->validate([
            'name' =>'required|string|max:255|unique:categories,name,'.$id,
            'description' =>'required|string',
            'image' => ['nullable','image',new FileTypeValidate(['jpg','jpeg','png'])]
        ]);

        $category = $id ? Category::findOrFail($id) : new Category();
        $category->name = $request->name;
        $category->slug = slug($request->name);
        $category->sort_description = $request->description;
        $category->is_trending = $request->is_trending ? Status::YES : Status::NO;
        if ($request->hasFile('image')) {
            try {
                $category->image = fileUploader($request->image, getFilePath('category'), getFileSize('category'), $category->image);
                $categoryKey = trim(getFilePath('category'), '/') . '/' . ltrim((string) $category->image, '/');
                if (env('UPLOAD_DISK', 's3') === 's3') {
                    try {
                        Storage::disk('s3')->setVisibility($categoryKey, 'public');
                    } catch (\Throwable $visibilityError) {
                        Log::warning('Failed to set category image visibility on S3', [
                            'category_id' => $id,
                            'key' => $categoryKey,
                            'error' => $visibilityError->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $th) {
                Log::error('Category image upload failed', [
                    'category_id' => $id,
                    'error' => $th->getMessage(),
                ]);
                $notify[] = ['error', 'Couldn\'t upload your image'];
                return back()->withNotify($notify);
            }
        }
        $category->save();

        $notify[]= $id ?  ['success', 'Category updated successfully'] : ['success', 'Category added successfully'];
        return back()->withNotify($notify);
    }



    public function subCategories(){
        $pageTitle = 'Subcategories';
        $subCategories = SubCategory::searchable(['name','category:name'])->paginate(getPaginate());
        $categories = Category::active()->get();
        return view('admin.sub_category.lists', compact('pageTitle', 'categories','subCategories'));
    }


    public function subCategoriesSave(Request $request, $id=null){
        $request->validate([
            'name' =>'required|string|max:255|unique:sub_categories,name,'.$request->id,
            'category_id' =>'required|integer'
        ]);

        $subCategory = $id? SubCategory::findOrFail($id) : new SubCategory();
        $subCategory->name = $request->name;
        $subCategory->category_id = $request->category_id;
        $subCategory->save();

        $notify[]= $request->id?  ['success', 'Subcategory updated successfully'] : ['success', 'Subcategory added successfully'];
        return back()->withNotify($notify);

    }



    public function status($id){
        return Category::changeStatus($id);
    }

    public function subCategoriesStatus($id){
        return SubCategory::changeStatus($id);
    }
    
}

<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use Illuminate\Http\Request;


class CouponController extends Controller {
    public function index() {
        $pageTitle = "All Coupons";
        $coupons   = Coupon::searchable(['coupon_name', 'coupon_code'])->paginate(getPaginate());
        return view('admin.coupons.index', compact('pageTitle', 'coupons'));
    }

    public function create() {
        $pageTitle  = "Create New Coupon";
        return view('admin.coupons.create', compact('pageTitle'));
    }

    public function edit($id) {
        $coupon = Coupon::whereId($id)->firstOrFail();
        $pageTitle  = "Edit Coupon";
        return view('admin.coupons.create', compact('pageTitle', 'coupon'));
    }

    public function save(Request $request, $id) {
        $this->validation($request, $id);

        if ($request->discount_type == Status::DISCOUNT_PERCENT && $request->amount > 100) {
            $notify[] = ['error', 'Coupon amount percentage can\'t be greater than 100%'];
            return back()->withNotify($notify);
        }

        if ($id == 0) {
            $coupon   = new Coupon();
            $notify[] = ['success', 'Coupon created successfully'];
        } else {
            $coupon   = Coupon::findOrFail($id);
            $notify[] = ['success', 'Coupon updated successfully'];
        }

        $coupon->coupon_name             = $request->coupon_name;
        $coupon->coupon_code             = $request->coupon_code;
        $coupon->discount_type           = $request->discount_type;
        $coupon->coupon_amount           = $request->amount;
        $coupon->expired_at              = $request->expired_at;
        $coupon->minimum_spend           = $request->minimum_spend;
        $coupon->maximum_spend           = $request->maximum_spend;
        $coupon->usage_limit_per_coupon  = $request->usage_limit_per_coupon;
        $coupon->usage_limit_per_user    = $request->usage_limit_per_customer;
        $coupon->usage_limit_per_user    = $request->usage_limit_per_customer;
        $coupon->usage_limit_per_user    = $request->usage_limit_per_customer;
        $coupon->exclude_sale_items      = $request->exclude_sale_items ? 1 : 0;

        $coupon->save();



        return to_route('admin.coupon.edit', $coupon->id)->withNotify($notify);
    }



    public function changeStatus($id) {
        return Coupon::changeStatus($id);
    
    }

    private function validation(Request $request, int $id) {
        $request->validate([
            "coupon_name"              => 'required|string|max:40',
            "coupon_code"              => 'required|string|max:40|unique:coupons,coupon_code,' . $id,
            "discount_type"            => 'required|in:1,2',
            "amount"                   => 'required|numeric',
            "expired_at"               => 'required|date|date_format:Y-m-d h:i A',
            "description"              => 'nullable|string',
            "minimum_spend"            => 'nullable|numeric|gt:0',
            "maximum_spend"            => 'nullable|numeric|gte:minimum_spend',
            "categories"               => 'nullable|array|min:1',
            "categories.*"             => 'required_with:categories|exists:categories,id',
            "products"                 => 'nullable|array|min:1',
            "products.*"               => 'required_with:products|exists:products,id',
            "usage_limit_per_coupon"   => 'nullable|integer',
            "usage_limit_per_customer" => 'nullable|integer',
            "exclude_sale_items"       => 'nullable|in:1'
        ]);
    }
}

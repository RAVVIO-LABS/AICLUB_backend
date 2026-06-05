@forelse ($topSellingCourses as $course)
    <div class="top-selling-product">
        <a href="{{ route('admin.courses.details', $course->slug) }}" data-placement="bottom" class="top-selling-thumb">
            <img src="{{ getImage(getFilePath('courseImage') . '/' . $course->image) }}" alt="image"
                class="top-selling-img">
        </a>
        <div class="top-selling-content">
            <a href="{{ route('admin.courses.details', $course->slug) }}" class="top-selling-title mb-2">
                {{ __($course->title) }}
            </a>


            <div class="top-selling-rating">
                @php
                    $rating = round($course->ratings['avg_rating']); // Round to the nearest whole number
                @endphp
                <ul class="rating">
                    @for ($i = 1; $i <= 5; $i++)
                        <li class="rating-item">
                            <i class="fas fa-star {{ $i <= $rating ? 'text-warning' : 'text-muted' }}"></i>
                        </li>
                    @endfor
                </ul>
            </div>

            <div class="flex-align gap-3">
                <p class="top-selling-price">
                    @if ($course->discount_price > 0)
                        <del>{{ showAmount($course->price) }}</del>
                        <span class="ms-2">
                            {{ showAmount($course->price - $course->discount_price) }}
                        </span>
                    @else
                        <span class="ms-2">{{ showAmount($course->price) }}</span>
                    @endif
                </p>
                <p class="top-selling-count">{{ $course->purchases_count }} @lang('Enrollments')</p>
            </div>
        </div>
    </div><!-- media end-->
@empty
    <div class="nodata-found text-center">
        <img alt="payment-thumb" src="{{ asset('assets/admin/images/no-data.png') }}">
        <p class="text-muted fw-semibold">@lang('No available courses') </p>
    </div>
@endforelse

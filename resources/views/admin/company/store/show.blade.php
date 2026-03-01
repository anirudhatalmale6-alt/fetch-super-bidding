@extends('admin.layouts.app')

@section('title', $pageTitle)

@section('content')

<section class="content">
    <div class="row">
        <!-- Product Images -->
        <div class="col-md-5">
            <div class="box">
                <div class="box-body">
                    @if($product->images && count($product->images) > 0)
                        <div id="product-images-carousel" class="carousel slide" data-ride="carousel">
                            <div class="carousel-inner">
                                @foreach($product->images as $index => $image)
                                <div class="carousel-item {{ $index == 0 ? 'active' : '' }}">
                                    <img src="{{ $image }}" class="d-block w-100" alt="{{ $product->name }}" 
                                         style="height: 400px; object-fit: cover; border-radius: 10px;">
                                </div>
                                @endforeach
                            </div>
                            @if(count($product->images) > 1)
                            <a class="carousel-control-prev" href="#product-images-carousel" role="button" data-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            </a>
                            <a class="carousel-control-next" href="#product-images-carousel" role="button" data-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            </a>
                            @endif
                        </div>
                        
                        @if(count($product->images) > 1)
                        <div class="row mt-3">
                            @foreach($product->images as $index => $image)
                            <div class="col-3">
                                <img src="{{ $image }}" class="img-thumbnail" 
                                     style="height: 80px; object-fit: cover; cursor: pointer;"
                                     onclick="$('.carousel').carousel({{ $index }})">
                            </div>
                            @endforeach
                        </div>
                        @endif
                    @else
                        <img src="{{ asset('images/default-product.png') }}" class="d-block w-100" 
                             style="height: 400px; object-fit: cover; border-radius: 10px;">
                    @endif
                    
                    @if($product->video_url)
                    <div class="mt-3">
                        <h5>Product Video</h5>
                        <div class="embed-responsive embed-responsive-16by9">
                            <iframe class="embed-responsive-item" src="{{ $product->video_url }}" allowfullscreen></iframe>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Product Info -->
        <div class="col-md-7">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $product->name }}</h3>
                    <a href="{{ route('company.store.index') }}" class="btn btn-default btn-sm pull-right">
                        <i class="fa fa-arrow-left mr-2"></i>Back to Store
                    </a>
                </div>
                <div class="box-body">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge badge-info mr-2">{{ $product->category }}</span>
                        @if($product->is_featured)
                            <span class="badge badge-warning mr-2"><i class="fa fa-star"></i> Featured</span>
                        @endif
                        @if($product->stock_quantity > 0)
                            <span class="badge badge-success"><i class="fa fa-check-circle"></i> In Stock ({{ $product->stock_quantity }})</span>
                        @else
                            <span class="badge badge-danger"><i class="fa fa-times-circle"></i> Out of Stock</span>
                        @endif
                    </div>

                    <div class="mb-4">
                        @if($product->has_discount)
                            <h2 class="text-success mb-0">${{ number_format($product->final_price, 2) }}</h2>
                            <del class="text-muted">${{ number_format($product->price, 2) }}</del>
                            <span class="badge badge-danger ml-2">Save {{ $product->discount_percentage }}%</span>
                        @else
                            <h2 class="text-primary mb-0">${{ number_format($product->price, 2) }}</h2>
                        @endif
                    </div>

                    <div class="mb-4">
                        <h5>Description</h5>
                        <p class="text-muted">{{ $product->description ?? 'No description available.' }}</p>
                    </div>

                    <div class="row mb-4">
                        <div class="col-6">
                            <h5>SKU</h5>
                            <p class="text-muted">{{ $product->sku }}</p>
                        </div>
                        <div class="col-6">
                            <h5>Target Audience</h5>
                            <p class="text-muted">{{ ucfirst($product->target_audience) }}</p>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex">
                        @if($product->stock_quantity > 0)
                            <a href="mailto:sales@example.com?subject=Pre-order: {{ urlencode($product->name) }}&body=I would like to pre-order {{ urlencode($product->name) }} (SKU: {{ $product->sku }})" 
                               class="btn btn-success btn-lg mr-3">
                                <i class="fa fa-envelope mr-2"></i>Contact to Pre-order
                            </a>
                        @else
                            <button class="btn btn-secondary btn-lg mr-3" disabled>
                                <i class="fa fa-times-circle mr-2"></i>Out of Stock
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    @if($relatedProducts->count() > 0)
    <div class="row mt-4">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Related Products</h4>
                </div>
                <div class="box-body">
                    <div class="row">
                        @foreach($relatedProducts as $related)
                        <div class="col-md-3 mb-3">
                            <div class="card h-100" style="border-radius: 10px; overflow: hidden;">
                                <img src="{{ $related->first_image }}" class="card-img-top" 
                                     style="height: 150px; object-fit: cover;">
                                <div class="card-body p-3">
                                    <h6 class="card-title">{{ Str::limit($related->name, 25) }}</h6>
                                    <p class="text-success font-weight-bold">${{ number_format($related->final_price, 2) }}</p>
                                    <a href="{{ route('company.store.show', $related->id) }}" class="btn btn-primary btn-sm btn-block">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</section>

@endsection
@extends('admin.layouts.app')

@section('title', $pageTitle)

@section('content')

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Add New Product</h4>
                    <a href="{{ route('market.index') }}" class="btn btn-default btn-sm pull-right">
                        <i class="fa fa-arrow-left mr-2"></i>Back to List
                    </a>
                </div>

                <form action="{{ route('market.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="box-body">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="name">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name') }}" required>
                                    @error('name')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="4">{{ old('description') }}</textarea>
                                    @error('description')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="price">Price <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-addon">$</span>
                                                <input type="number" step="0.01" min="0" 
                                                       class="form-control @error('price') is-invalid @enderror" 
                                                       id="price" name="price" value="{{ old('price') }}" required>
                                            </div>
                                            @error('price')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="discount_price">Discount Price</label>
                                            <div class="input-group">
                                                <span class="input-group-addon">$</span>
                                                <input type="number" step="0.01" min="0" 
                                                       class="form-control @error('discount_price') is-invalid @enderror" 
                                                       id="discount_price" name="discount_price" value="{{ old('discount_price') }}">
                                            </div>
                                            @error('discount_price')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="stock_quantity">Stock Quantity <span class="text-danger">*</span></label>
                                            <input type="number" min="0" 
                                                   class="form-control @error('stock_quantity') is-invalid @enderror" 
                                                   id="stock_quantity" name="stock_quantity" value="{{ old('stock_quantity', 0) }}" required>
                                            @error('stock_quantity')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="sku">SKU</label>
                                            <input type="text" class="form-control @error('sku') is-invalid @enderror" 
                                                   id="sku" name="sku" value="{{ old('sku') }}" placeholder="Auto-generated if empty">
                                            @error('sku')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="category">Category <span class="text-danger">*</span></label>
                                            <select class="form-control @error('category') is-invalid @enderror" 
                                                    id="category" name="category" required>
                                                <option value="">Select Category</option>
                                                @foreach($categories as $key => $value)
                                                    <option value="{{ $key }}" {{ old('category') == $key ? 'selected' : '' }}>{{ $value }}</option>
                                                @endforeach
                                            </select>
                                            @error('category')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="target_audience">Target Audience <span class="text-danger">*</span></label>
                                            <select class="form-control @error('target_audience') is-invalid @enderror" 
                                                    id="target_audience" name="target_audience" required>
                                                @foreach($targetAudiences as $key => $value)
                                                    <option value="{{ $key }}" {{ old('target_audience', 'all') == $key ? 'selected' : '' }}>{{ $value }}</option>
                                                @endforeach
                                            </select>
                                            @error('target_audience')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Media & Settings -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="images">Product Images</label>
                                    <input type="file" class="form-control @error('images') is-invalid @enderror" 
                                           id="images" name="images[]" multiple accept="image/*">
                                    <small class="text-muted">You can select multiple images. Max 5MB each.</small>
                                    @error('images')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="video_url">Product Video URL</label>
                                    <input type="url" class="form-control @error('video_url') is-invalid @enderror" 
                                           id="video_url" name="video_url" value="{{ old('video_url') }}" placeholder="https://...">
                                    @error('video_url')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="banner_image">Banner Image</label>
                                    <input type="file" class="form-control @error('banner_image') is-invalid @enderror" 
                                           id="banner_image" name="banner_image" accept="image/*">
                                    <small class="text-muted">Used for store/shop banner. Max 5MB.</small>
                                    @error('banner_image')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="banner_video_url">Banner Video URL</label>
                                    <input type="url" class="form-control @error('banner_video_url') is-invalid @enderror" 
                                           id="banner_video_url" name="banner_video_url" value="{{ old('banner_video_url') }}" placeholder="https://...">
                                    @error('banner_video_url')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <div class="checkbox">
                                        <input type="checkbox" id="is_featured" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }}>
                                        <label for="is_featured">Featured Product</label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="checkbox">
                                        <input type="checkbox" id="status" name="status" value="1" {{ old('status', true) ? 'checked' : '' }}>
                                        <label for="status">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save mr-2"></i>Save Product
                        </button>
                        <a href="{{ route('market.index') }}" class="btn btn-default">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

@endsection

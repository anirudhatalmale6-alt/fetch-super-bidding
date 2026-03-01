<div class="box-body">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Target</th>
                    <th>Featured</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                <tr>
                    <td>{{ $product->id }}</td>
                    <td>
                        <img src="{{ $product->first_image }}" alt="{{ $product->name }}" 
                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                    </td>
                    <td>{{ Str::limit($product->name, 30) }}</td>
                    <td>{{ $product->sku }}</td>
                    <td><span class="badge badge-info">{{ $product->category }}</span></td>
                    <td>
                        @if($product->has_discount)
                            <del class="text-muted">${{ number_format($product->price, 2) }}</del><br>
                            <span class="text-success font-weight-bold">${{ number_format($product->final_price, 2) }}</span>
                        @else
                            ${{ number_format($product->price, 2) }}
                        @endif
                    </td>
                    <td>
                        @if($product->stock_quantity > 10)
                            <span class="badge badge-success">{{ $product->stock_quantity }}</span>
                        @elseif($product->stock_quantity > 0)
                            <span class="badge badge-warning">{{ $product->stock_quantity }}</span>
                        @else
                            <span class="badge badge-danger">Out of Stock</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-secondary">{{ ucfirst($product->target_audience) }}</span>
                    </td>
                    <td>
                        <button class="btn btn-sm {{ $product->is_featured ? 'btn-warning' : 'btn-default' }} toggle-featured" 
                                data-url="{{ route('market.toggle-featured', $product->id) }}"
                                title="{{ $product->is_featured ? 'Remove from Featured' : 'Add to Featured' }}">
                            <i class="fa fa-star{{ $product->is_featured ? '' : '-o' }}"></i>
                        </button>
                    </td>
                    <td>
                        <button class="btn btn-sm {{ $product->status ? 'btn-success' : 'btn-danger' }} toggle-status" 
                                data-url="{{ route('market.toggle-status', $product->id) }}">
                            {{ $product->status ? 'Active' : 'Inactive' }}
                        </button>
                    </td>
                    <td>
                        <a href="{{ route('market.edit', $product->id) }}" class="btn btn-sm btn-info" title="Edit">
                            <i class="fa fa-edit"></i>
                        </a>
                        <button class="btn btn-sm btn-danger sweet-delete" 
                                data-url="{{ route('market.destroy', $product->id) }}" title="Delete">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="text-center">No products found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($products->hasPages())
<div class="box-footer">
    <div class="pull-right">
        {{ $products->links() }}
    </div>
</div>
@endif

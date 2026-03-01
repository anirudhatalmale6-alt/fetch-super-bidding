@extends('admin.layouts.app')

@section('title', $pageTitle)

@section('content')

<!-- Start Page content -->
<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border">
                    <div class="row text-right">
                        <div class="col-8 col-md-3">
                            <div class="form-group">
                                <div class="controls">
                                    <input type="text" id="search_keyword" name="search" class="form-control"
                                        placeholder="Enter keyword">
                                </div>
                            </div>
                        </div>

                        <div class="col-4 col-md-2 text-left">
                            <button id="search" class="btn btn-success btn-outline btn-sm py-2" type="submit">
                                Search
                            </button>
                        </div>

                        <div class="col-md-7 text-center text-md-right">
                            <a href="{{ route('market.create') }}" class="btn btn-primary btn-sm">
                                <i class="mdi mdi-plus-circle mr-2"></i>Add Product
                            </a>
                        </div>
                    </div>
                </div>

                <div id="js-market-partial-target">
                    <include-fragment src="{{ route('market.fetch') }}">
                        <span style="text-align: center;font-weight: bold;">Loading...</span>
                    </include-fragment>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="{{ asset('assets/js/fetchdata.min.js') }}"></script>
<script>
    var search_keyword = '';
    $(function() {
        $('body').on('click', '.pagination a', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            $.get(url, $('#search').serialize(), function(data) {
                $('#js-market-partial-target').html(data);
            });
        });

        $('#search').on('click', function(e) {
            e.preventDefault();
            search_keyword = $('#search_keyword').val();
            fetchMarketData();
        });
    });

    function fetchMarketData() {
        fetch('{{ route('market.fetch') }}?search=' + search_keyword)
            .then(response => response.text())
            .then(html => {
                document.querySelector('#js-market-partial-target').innerHTML = html;
            });
    }

    $(document).on('click', '.sweet-delete', function(e) {
        e.preventDefault();
        let url = $(this).attr('data-url');

        swal({
            title: "Are you sure to delete?",
            type: "error",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "Delete",
            cancelButtonText: "No! Keep it",
            closeOnConfirm: false,
            closeOnCancel: true
        }, function(isConfirm) {
            if (isConfirm) {
                swal.close();
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(res) {
                        fetchMarketData();
                        $.toast({
                            heading: 'Success',
                            text: res.success,
                            position: 'top-right',
                            loaderBg: '#ff6849',
                            icon: 'success',
                            hideAfter: 5000,
                            stack: 1
                        });
                    }
                });
            }
        });
    });

    $(document).on('click', '.toggle-status', function(e) {
        e.preventDefault();
        let url = $(this).attr('data-url');
        let button = $(this);
        
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                fetchMarketData();
                $.toast({
                    heading: 'Success',
                    text: res.message,
                    position: 'top-right',
                    loaderBg: '#ff6849',
                    icon: 'success',
                    hideAfter: 3000,
                    stack: 1
                });
            }
        });
    });

    $(document).on('click', '.toggle-featured', function(e) {
        e.preventDefault();
        let url = $(this).attr('data-url');
        
        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(res) {
                fetchMarketData();
                $.toast({
                    heading: 'Success',
                    text: res.message,
                    position: 'top-right',
                    loaderBg: '#ff6849',
                    icon: 'success',
                    hideAfter: 3000,
                    stack: 1
                });
            }
        });
    });
</script>

@endsection

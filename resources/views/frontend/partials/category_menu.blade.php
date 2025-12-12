<div style="height:454px;" class="aiz-category-menu bg-white rounded @if (Route::currentRouteName() == 'home') shadow-sm @else shadow-lg @endif" id="category-sidebar">
    <div class="p-3 mz-secondary text-white d-none d-lg-block rounded-top all-category position-relative text-left">
        <span class="fw-600 fs-16 mr-3">{{ translate('Categories') }}</span>
        <a href="{{ route('categories.all') }}" class="text-white opacity-80 float-right">
            <span class="d-none d-lg-inline-block">{{ translate('See All') }} ></span>
        </a>
    </div>
    <ul style="font-size:0.8em;" class="list-unstyled categories no-scrollbar py-2 mb-0 text-left">
        @php
        /*
        $category_menu = DB::table('products')
          ->leftJoin('category_groups', 'products.group_id', '=', 'category_groups.id')
          ->where('category_groups.featured', 1)
          ->where('products.part_no', '!=', '')
          ->where('products.current_stock', '>', 0)
          ->orderByRaw("CASE WHEN category_groups.name = 'Power Tools' THEN 0 ELSE 1 END")
          ->orderBy('category_groups.name', 'asc')
          ->select('category_groups.*')
          ->distinct()
          ->get();
          */

          $category_menu = DB::table('products')
          ->leftJoin('category_groups', 'products.group_id', '=', 'category_groups.id')
          ->leftJoin('uploads as banner_upload', 'category_groups.banner', '=', 'banner_upload.id')
          ->leftJoin('uploads as icon_upload', 'category_groups.icon', '=', 'icon_upload.id')
          ->where('category_groups.featured', 1)
          ->where('products.part_no', '!=', '')
          ->where('products.current_stock', '>', 0)
          ->orderByRaw("CASE 
              WHEN category_groups.id = 1 THEN 0 
              WHEN category_groups.id = 8 THEN 1 
              ELSE 2 
          END")
          ->orderBy('category_groups.name', 'asc')
          ->select('category_groups.*', 'banner_upload.file_name as banner_image', 'icon_upload.file_name as icon_image')
          ->distinct()
          ->get();


          
        @endphp
        @foreach ($category_menu as $category)

            @php
              $file_path = $category->icon_image
                  ? env('UPLOADS_BASE_URL', url('public')) . '/' . $category->icon_image
                  : url('public/assets/img/placeholder.jpg');
            @endphp

            <li  class="category-nav-element position-relative" data-id="{{ $category->id }}">
                <!-- <a href="#{{ route('products.category', $category->slug) }}" class="text-truncate text-reset py-2 px-3 d-block"> -->
                <a href="{{ route('group.category.products', ['category_group__id' => $category->id]) }}" class="text-truncate text-reset py-2 px-3 d-block">  
                    <!-- <img class="cat-image lazyload mr-2 opacity-70" src="{{ static_asset('assets/img/placeholder.jpg') }}" data-src="{{ uploaded_asset($category->icon) }}" width="16" alt="{{ $category->name }}" onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                    <img class="cat-image lazyload mr-2 opacity-70" src="{{  $file_path }}" data-src="{{  $file_path }}" width="16" alt="{{ $category->name }}" onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';">
                    <span class="cat-name">{{ $category->name }}</span>
                </a>
                <div class="test">
                    @php
                    $sub_category_menu = DB::table('products')
                      ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                      ->where('categories.category_group_id', $category->id)
                      ->where('products.part_no','!=','')
                      ->where('products.current_stock','>','0')
                      ->orderBy('categories.name', 'asc')
                      ->select('categories.id', 'categories.name', 'categories.slug')
                      ->distinct()
                      ->get();
                    @endphp
                   <ul class="sub-category-list">
                    @foreach ($sub_category_menu as $subcategory)
                        @if (!empty($subcategory->slug))
                            <li><a href="{{ route('products.category', $subcategory->slug) }}">{{ $subcategory->name }}</a></li>
                        @endif
                    @endforeach
                </ul>
                </div>
            </li>
        @endforeach
    </ul>
</div>

<style>

  .category-nav-element {
    position: relative;
  }

  .test {
    padding: 10px;
    position: absolute;
    top: 0;
    left: 100%; /* Position the submenu to the right of the parent element */
    z-index: 1000;
    background-color: #fff;
    display: none;
    max-height: 400px; /* Maximum height of the submenu before scrollbar appears */
    overflow-y: auto; /* Enable vertical scrollbar */
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.1); /* Optional: Add shadow for better visual separation */
    width: 700px; /* Adjust as needed */
  }

  .category-nav-element:hover .test {
    display: block;
  }

  .sub-category-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex; /* Display submenu items in a row */
    flex-wrap: wrap; /* Allow items to wrap */
  }

  .sub-category-list li {
    flex: 0 0 33.33%; /* Three columns, adjust width as needed */
    padding: 4px; /* Adjust spacing between items */
    box-sizing: border-box; /* Ensure padding doesn't affect width */
  }

  .sub-category-list li a {
    display: block;
    color: #333;
    text-decoration: none;
    transition: all 0.3s ease;
    padding: 8px; /* Padding around the link */
  }

  .sub-category-list li a:hover {
    text-decoration: underline;
  }

  /* Custom scrollbar styles */
  .test::-webkit-scrollbar {
    width: 10px; /* Width of the scrollbar */
  }

  .test::-webkit-scrollbar-track {
    background: #f1f1f1; /* Track color */
  }

  .test::-webkit-scrollbar-thumb {
    background: #888; /* Thumb color */
    border-radius: 5px; /* Rounded corners */
  }

  .test::-webkit-scrollbar-thumb:hover {
    background: #555; /* Hover state of the thumb */
  }
</style>

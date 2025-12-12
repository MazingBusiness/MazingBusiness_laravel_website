<div class="card-columns">
  @foreach ($category->childrenCategoriesMini as $key => $first_level_id)
    @if ($first_level_id->products_count)
      <div class="card shadow-none border-0">
        <ul class="list-unstyled mb-3">
          <li class="fw-600 pb-2 mb-3">
            <a class="text-reset"
              href="{{ route('products.category', $first_level_id->slug) }}">{{ $first_level_id->getTranslation('name') }}</a>
          </li>
          @foreach ($first_level_id->categories as $key => $second_level_id)
            @if ($second_level_id->products_count)
              <li class="mb-2">
                <a class="text-reset"
                  href="{{ route('products.category', $second_level_id->slug) }}">{{ $second_level_id->getTranslation('name') }}</a>
              </li>
            @endif
          @endforeach
        </ul>
      </div>
    @endif
  @endforeach
</div>

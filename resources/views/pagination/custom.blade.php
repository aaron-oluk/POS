@if ($paginator->hasPages())
<div class="pagination-wrap">
  <nav>
    <div>
      @if ($paginator->onFirstPage())
        <span class="filter-chip" style="opacity:0.5;">Prev</span>
      @else
        <a href="{{ $paginator->previousPageUrl() }}" class="filter-chip">Prev</a>
      @endif

      @foreach ($elements as $element)
        @if (is_string($element))
          <span class="filter-chip" style="opacity:0.5;">{{ $element }}</span>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <span class="filter-chip active">{{ $page }}</span>
            @else
              <a href="{{ $url }}" class="filter-chip">{{ $page }}</a>
            @endif
          @endforeach
        @endif
      @endforeach

      @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="filter-chip">Next</a>
      @else
        <span class="filter-chip" style="opacity:0.5;">Next</span>
      @endif
    </div>
  </nav>
</div>
@endif

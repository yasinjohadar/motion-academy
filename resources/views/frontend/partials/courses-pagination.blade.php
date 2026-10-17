@if ($courses->hasPages())
    <nav class="courses-pagination section-fade-up" aria-label="صفحات الكورسات">
        <ul class="pagination courses-ajax-pagination justify-content-center mb-0">
            @if ($courses->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i> السابق</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $courses->previousPageUrl() }}" data-page="{{ $courses->currentPage() - 1 }}"><i class="fas fa-chevron-right"></i> السابق</a></li>
            @endif

            @foreach ($courses->getUrlRange(1, $courses->lastPage()) as $page => $url)
                <li class="page-item {{ $page == $courses->currentPage() ? 'active' : '' }}" @if($page == $courses->currentPage()) aria-current="page" @endif>
                    <a class="page-link" href="{{ $url }}" data-page="{{ $page }}">{{ $page }}</a>
                </li>
            @endforeach

            @if ($courses->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $courses->nextPageUrl() }}" data-page="{{ $courses->currentPage() + 1 }}">التالي <i class="fas fa-chevron-left"></i></a></li>
            @else
                <li class="page-item disabled"><span class="page-link">التالي <i class="fas fa-chevron-left"></i></span></li>
            @endif
        </ul>
    </nav>
@endif

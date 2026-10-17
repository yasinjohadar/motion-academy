{{-- Grid column around the course card component (used by pages and the AJAX catalogue). --}}
<div class="{{ $colClass ?? 'col-md-6 col-lg-4' }}">
    <x-frontend.course-card :course="$course" />
</div>

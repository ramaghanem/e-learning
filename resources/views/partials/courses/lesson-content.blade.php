<div class="panel mb-4">

    @if ($lesson->type === 'video')

        @php
            $embedUrl = $lesson->videoEmbedUrl();
        @endphp

        @if ($lesson->video_path)
            <video
                class="w-100 rounded"
                controls
                preload="metadata"
                @if ($lesson->video_thumbnail) poster="{{ asset('storage/' . $lesson->video_thumbnail) }}" @endif
            >
                <source src="{{ asset('storage/' . $lesson->video_path) }}">
                Your browser cannot play this video.
            </video>
        @elseif ($embedUrl)
            {{-- YouTube or Vimeo player --}}
            <div class="ratio ratio-16x9">
                <iframe
                    src="{{ $embedUrl }}"
                    title="{{ $lesson->title }}"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; fullscreen; gyroscope; picture-in-picture"
                    allowfullscreen
                    class="rounded"
                ></iframe>
            </div>
        @elseif ($lesson->video_url)
            <x-ui.empty-state icon="bi-box-arrow-up-right" title="External video" class="border-0">
                This video is hosted on another website.

                <x-slot:action>
                    <a href="{{ $lesson->video_url }}" target="_blank" rel="noopener" class="btn btn-primary">
                        Open Video
                    </a>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <x-ui.empty-state icon="bi-camera-video-off" title="No video added yet" class="border-0" />
        @endif

    @elseif ($lesson->type === 'article')

        @if (filled($lesson->content))
            <div>{!! nl2br(e($lesson->content)) !!}</div>
        @else
            <x-ui.empty-state icon="bi-file-earmark-text" title="No article content yet" class="border-0" />
        @endif

    @else

        @include('partials.courses.quizzes.start')

    @endif

</div>

@if ($lesson->resources->isNotEmpty())
    <div class="panel mb-4">
        <h2 class="panel-title">Downloadable Resources</h2>

        <ul class="list-unstyled mb-0">
            @foreach ($lesson->resources as $resource)
                <li class="mb-1">
                    <i class="bi bi-paperclip me-1"></i>
                    <a href="{{ asset('storage/' . $resource->file_path) }}" download="{{ $resource->original_name }}">
                        {{ $resource->original_name }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif

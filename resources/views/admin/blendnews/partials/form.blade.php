@csrf

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Review the form:</strong> {{ $errors->first() }}
    </div>
@endif

@php
    $featuredImagePath = old('featured_image_path', data_get($post->featured_image, 'path'));
    $featuredImageAlt = old('featured_image_alt', data_get($post->featured_image, 'alt'));
    $selectedCategories = collect(old('categories', $post->exists ? $post->categories->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $selectedTags = collect(old('tags', $post->exists ? $post->tags->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
@endphp

<div class="row">
    <div class="col-lg-8">
        <div class="form-group">
            <label for="title">Title <span class="text-danger">*</span></label>
            <input id="title" name="title" class="form-control" value="{{ old('title', $post->title) }}" required>
        </div>

        <div class="form-group">
            <label for="slug">Slug</label>
            <input id="slug" name="slug" class="form-control" value="{{ old('slug', $post->slug) }}" placeholder="Auto-generated if blank">
        </div>

        <div class="form-group">
            <label for="excerpt">Excerpt</label>
            <textarea id="excerpt" name="excerpt" class="form-control" rows="3" maxlength="1000">{{ old('excerpt', $post->excerpt) }}</textarea>
        </div>

        <div class="form-group">
            <label for="content">Content <span class="text-danger">*</span></label>
            <textarea id="content" name="content" class="form-control cms-editor-field" rows="18" required>{{ old('content', $post->content) }}</textarea>
            <div id="blendnews-cms-editor" class="blendnews-cms-editor"></div>
            <small class="form-text text-muted">Use the editor toolbar for headings, formatting, links, lists, quotes, and embeds.</small>
        </div>

        <div class="card bg-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-search mr-1"></i> SEO
                </h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="seo_title">SEO Title</label>
                    <input id="seo_title" name="seo_title" class="form-control" value="{{ old('seo_title', data_get($post->seo, 'title')) }}">
                </div>
                <div class="form-group mb-0">
                    <label for="seo_description">SEO Description</label>
                    <textarea id="seo_description" name="seo_description" class="form-control" rows="3" maxlength="500">{{ old('seo_description', data_get($post->seo, 'description')) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card bg-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cog mr-1"></i> Publishing
                </h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        @foreach ($statuses as $statusKey => $statusLabel)
                            <option value="{{ $statusKey }}" @selected(old('status', $post->status) === $statusKey)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="published_at">Publish Date</label>
                    <input
                        id="published_at"
                        name="published_at"
                        type="datetime-local"
                        class="form-control"
                        value="{{ old('published_at', $post->published_at ? $post->published_at->format('Y-m-d\TH:i') : '') }}"
                    >
                </div>

                <div class="form-group">
                    <label for="author_id">Author</label>
                    <select id="author_id" name="author_id" class="form-control">
                        <option value="">Internal / unassigned</option>
                        @foreach ($authors as $author)
                            <option value="{{ $author->id }}" @selected((int) old('author_id', $post->author_id) === $author->id)>
                                {{ $author->name }} ({{ $author->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="importance_level">Importance Level</label>
                    <select id="importance_level" name="importance_level" class="form-control">
                        @for ($level = 1; $level <= 5; $level++)
                            <option value="{{ $level }}" @selected((int) old('importance_level', $post->importance_level ?: 1) === $level)>
                                Level {{ $level }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_featured" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured))>
                    <label class="custom-control-label" for="is_featured">Featured Story</label>
                </div>
                <div class="custom-control custom-switch mt-2">
                    <input type="checkbox" class="custom-control-input" id="is_breaking" name="is_breaking" value="1" @checked(old('is_breaking', $post->is_breaking))>
                    <label class="custom-control-label" for="is_breaking">Breaking News</label>
                </div>
            </div>
        </div>

        <div class="card bg-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-check-circle mr-1"></i> Verification
                </h3>
            </div>
            <div class="card-body">
                <div class="form-group mb-0">
                    <label for="verification_status">Verification Status</label>
                    <select id="verification_status" name="verification_status" class="form-control">
                        @foreach ($verificationStatuses as $verificationStatus)
                            <option value="{{ $verificationStatus }}" @selected(old('verification_status', $post->verification_status ?: 'unverified') === $verificationStatus)>
                                {{ ucfirst($verificationStatus) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card bg-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-image mr-1"></i> Featured Image
                </h3>
            </div>
            <div class="card-body">
                @if ($featuredImagePath)
                    <div class="mb-3 border">
                        <img src="{{ asset('media/'.ltrim($featuredImagePath, '/')) }}" alt="" class="img-fluid w-100" style="max-height: 180px; object-fit: cover;">
                    </div>
                @endif
                <div class="form-group">
                    <label for="featured_image_path">Media Path</label>
                    <input id="featured_image_path" name="featured_image_path" class="form-control" value="{{ $featuredImagePath }}" placeholder="news/story-image.jpg">
                    <small class="form-text text-muted">Use a path under public media, without the leading /media when possible.</small>
                </div>
                <div class="form-group mb-0">
                    <label for="featured_image_alt">Alt Text</label>
                    <input id="featured_image_alt" name="featured_image_alt" class="form-control" value="{{ $featuredImageAlt }}">
                </div>
            </div>
        </div>

        <div class="card bg-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-folder mr-1"></i> Organization
                </h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="category_id">Primary Category</label>
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">No primary category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('category_id', $post->category_id) === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="categories">Categories</label>
                    <select id="categories" name="categories[]" class="form-control" multiple size="5">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($selectedCategories->contains($category->id))>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="tags">Tags</label>
                    <select id="tags" name="tags[]" class="form-control" multiple size="5">
                        @foreach ($tags as $tag)
                            <option value="{{ $tag->id }}" @selected($selectedTags->contains($tag->id))>{{ $tag->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="news_source_id">News Source</label>
                    <select id="news_source_id" name="news_source_id" class="form-control">
                        <option value="">Internal</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected((int) old('news_source_id', $post->news_source_id) === $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group mb-0">
                    <label for="news_event_id">Related Event</label>
                    <select id="news_event_id" name="news_event_id" class="form-control">
                        <option value="">No event</option>
                        @foreach ($events as $event)
                            <option value="{{ $event->id }}" @selected((int) old('news_event_id', $post->news_event_id) === $event->id)>{{ $event->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

    <style>
        .cms-editor-field {
            display: none;
        }

        .blendnews-cms-editor {
            min-height: 460px;
            background: #0b0b0b;
            border: 1px solid #343a40;
        }

        .ql-toolbar.ql-snow {
            position: sticky;
            top: 56px;
            z-index: 20;
            border-color: #343a40;
            background: #111;
            color: #e9ecef;
        }

        .ql-container.ql-snow {
            border-color: #343a40;
            background: #080808;
        }

        .ql-editor {
            min-height: 460px;
            color: #f8f9fa;
            font-size: 16px;
            line-height: 1.65;
        }

        .ql-editor h1,
        .ql-editor h2,
        .ql-editor h3,
        .ql-editor p,
        .ql-editor li,
        .ql-editor blockquote,
        .ql-editor span {
            color: #f8f9fa;
        }

        .ql-editor blockquote {
            border-left: 4px solid #ff1f1f;
            background: #151515;
            padding: .75rem 1rem;
        }

        .ql-editor.ql-blank::before {
            color: #868e96;
        }

        .ql-snow .ql-stroke {
            stroke: #ced4da;
        }

        .ql-snow .ql-fill,
        .ql-snow .ql-stroke.ql-fill {
            fill: #ced4da;
        }

        .ql-snow .ql-picker {
            color: #e9ecef;
        }

        .ql-snow .ql-picker-options {
            background: #111;
            border-color: #343a40;
        }

        .ql-snow .ql-picker-item {
            color: #e9ecef;
        }

        .ql-snow.ql-toolbar button:hover .ql-stroke,
        .ql-snow .ql-toolbar button:hover .ql-stroke,
        .ql-snow.ql-toolbar button.ql-active .ql-stroke,
        .ql-snow .ql-toolbar button.ql-active .ql-stroke {
            stroke: #ff1f1f;
        }

        .ql-snow.ql-toolbar button:hover .ql-fill,
        .ql-snow .ql-toolbar button:hover .ql-fill,
        .ql-snow.ql-toolbar button.ql-active .ql-fill,
        .ql-snow .ql-toolbar button.ql-active .ql-fill {
            fill: #ff1f1f;
        }

        .ql-snow.ql-toolbar .ql-picker-label:hover,
        .ql-snow .ql-toolbar .ql-picker-label:hover,
        .ql-snow.ql-toolbar .ql-picker-label.ql-active,
        .ql-snow .ql-toolbar .ql-picker-label.ql-active,
        .ql-snow.ql-toolbar .ql-picker-item:hover,
        .ql-snow .ql-toolbar .ql-picker-item:hover {
            color: #ff1f1f;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const contentField = document.getElementById('content');
            const editorElement = document.getElementById('blendnews-cms-editor');

            if (!contentField || !editorElement || typeof Quill === 'undefined') {
                return;
            }

            const editor = new Quill(editorElement, {
                theme: 'snow',
                placeholder: 'Write the BlendNews story...',
                modules: {
                    toolbar: [
                        [{ header: [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ color: [] }, { background: [] }],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ align: [] }],
                        ['blockquote', 'code-block'],
                        ['link', 'image', 'video'],
                        ['clean']
                    ],
                    history: {
                        delay: 1000,
                        maxStack: 80,
                        userOnly: true
                    }
                }
            });

            if (contentField.value) {
                editor.root.innerHTML = contentField.value;
            }

            const syncContent = function () {
                contentField.value = editor.root.innerHTML;
            };

            editor.on('text-change', syncContent);

            document.querySelectorAll('form').forEach(function (form) {
                form.addEventListener('submit', syncContent);
            });
        });
    </script>
@endonce

<div class="d-flex justify-content-between">
    <a href="{{ route('admin.blendnews.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to BlendNews
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-1"></i> Save Story
    </button>
</div>

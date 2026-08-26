@extends('layouts.app')

@section('content')
    <style>
        .guide-hero {
            background: linear-gradient(125deg, #003b78 0%, #005eb8 52%, #1785d1 100%);
            border-radius: 1.5rem;
            color: #fff;
            overflow: hidden;
            padding: 3rem;
            position: relative;
        }

        .guide-hero::after {
            background: rgba(255, 255, 255, .1);
            border-radius: 50%;
            content: '';
            height: 22rem;
            position: absolute;
            right: -7rem;
            top: -11rem;
            width: 22rem;
        }

        .guide-hero-content {
            position: relative;
            z-index: 1;
        }

        .guide-hero-icon {
            align-items: center;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 1rem;
            display: flex;
            font-size: 2.25rem;
            height: 4.5rem;
            justify-content: center;
            width: 4.5rem;
        }

        .guide-hero h1 {
            font-size: clamp(1.8rem, 4vw, 2.6rem);
            letter-spacing: -.03em;
        }

        .guide-role-badge {
            background: rgba(255, 255, 255, .15);
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 999px;
            display: inline-flex;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .06em;
            margin-top: 1.25rem;
            padding: .45rem .8rem;
            text-transform: uppercase;
        }

        .guide-layout {
            display: grid;
            grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
            gap: 2rem;
        }

        .guide-toc {
            align-self: start;
            border: 1px solid #e6edf5 !important;
            border-radius: 1.25rem;
            position: sticky;
            top: 1rem;
        }

        .guide-toc-title {
            color: #003b78;
            letter-spacing: .06em;
        }

        .guide-toc a {
            border-left: 3px solid transparent;
            border-radius: 0 .5rem .5rem 0;
            color: #536273;
            display: block;
            font-size: .875rem;
            line-height: 1.35;
            margin: .2rem 0;
            padding: .65rem .8rem;
            text-decoration: none;
            transition: all .18s ease-in-out;
        }

        .guide-toc a:hover,
        .guide-toc a:focus {
            background: #eaf4ff;
            border-left-color: #006cc9;
            color: #004aad;
            transform: translateX(2px);
        }

        .guide-section {
            border: 1px solid #e6edf5;
            border-radius: 1.25rem;
            scroll-margin-top: 1rem;
            transition: box-shadow .2s ease, transform .2s ease;
        }

        .guide-section + .guide-section {
            margin-top: 1.5rem;
        }

        .guide-section:hover {
            box-shadow: 0 .75rem 2rem rgba(30, 75, 120, .1) !important;
            transform: translateY(-2px);
        }

        .guide-section-heading {
            align-items: center;
            color: #003b78;
            display: flex;
            font-size: 1.2rem;
            font-weight: 700;
            gap: .9rem;
            margin-bottom: 1.35rem;
        }

        .guide-section-number {
            align-items: center;
            background: linear-gradient(135deg, #006cc9, #1590dd);
            border-radius: 50%;
            color: #fff;
            display: inline-flex;
            font-size: .85rem;
            font-weight: 800;
            height: 2.25rem;
            justify-content: center;
            line-height: 1;
            width: 2.25rem;
        }

        .guide-copy {
            background: #f8fbfe;
            border-left: 4px solid #b7ddfb;
            border-radius: 0 .75rem .75rem 0;
            color: #344454;
            font-size: .95rem;
            line-height: 1.8;
            padding: 1.25rem 1.4rem;
        }

        .guide-copy p:last-child,
        .guide-copy ul:last-child,
        .guide-copy ol:last-child {
            margin-bottom: 0;
        }

        .guide-copy-label {
            color: #004aad;
            font-size: .85rem;
            font-weight: 800;
            letter-spacing: .04em;
            margin: 1.25rem 0 .55rem;
            text-transform: uppercase;
        }

        .guide-copy-label:first-child {
            margin-top: 0;
        }

        .guide-copy ol,
        .guide-copy ul {
            margin: .65rem 0 1.1rem;
            padding-left: 1.35rem;
        }

        .guide-copy li {
            margin-bottom: .65rem;
            padding-left: .35rem;
        }

        .guide-copy ol li::marker {
            color: #006cc9;
            font-weight: 800;
        }

        .guide-note {
            background: #fff8e6;
            border: 1px solid #ffe29a;
            border-radius: 1rem;
            color: #705300;
            padding: 1rem 1.25rem;
        }

        @media (max-width: 991.98px) {
            .guide-layout {
                display: block;
            }

            .guide-toc {
                margin-bottom: 1.25rem;
                position: static;
            }

            .guide-toc-links {
                max-height: 14rem;
                overflow-y: auto;
            }
        }

        @media (max-width: 575.98px) {
            .guide-hero {
                border-radius: 1rem;
                padding: 1.75rem;
            }

            .guide-hero-icon {
                font-size: 1.75rem;
                height: 3.5rem;
                width: 3.5rem;
            }

            .guide-section .card-body {
                padding: 1.25rem !important;
            }

            .guide-copy {
                font-size: .9rem;
                padding: 1rem;
            }
        }
    </style>

    <main class="main">
        <div class="responsive-wrapper">
            <div class="guide-hero shadow">
                <div class="guide-hero-content d-flex align-items-center gap-3">
                    <div class="guide-hero-icon"><i class="bx bx-book-open"></i></div>
                    <div>
                        <div class="text-uppercase small fw-bold opacity-75 mb-2">Help Center</div>
                        <h1 class="mb-2 text-white">{{ $title }}</h1>
                        <p class="mb-0 opacity-75">Find the module you need, then follow the steps one at a time.</p>
                        <span class="guide-role-badge"><i class="bx bx-user-check me-1"></i> Admin Reference</span>
                    </div>
                </div>
            </div>

            <div class="inner-content mt-5 pb-5">
                <div class="guide-layout">
                    <aside class="guide-toc card shadow-sm">
                        <div class="card-body p-3 p-md-4">
                            <div class="guide-toc-title text-uppercase fw-bold small mb-3"><i class="bx bx-list-ul me-1"></i> Quick navigation</div>
                            <nav class="guide-toc-links" aria-label="Guide sections">
                                @foreach($sections as $section)
                                    <a href="#{{ $section['id'] }}">{{ $section['heading'] }}</a>
                                @endforeach
                            </nav>
                        </div>
                    </aside>

                    <div>
                        <div class="guide-note shadow-sm mb-4" role="alert">
                            <i class="bx bx-shield-quarter me-2 fs-5 align-middle"></i>
                            Confirm the account, reference number, and billing period before saving any transaction.
                        </div>

                        @foreach($sections as $section)
                            <section id="{{ $section['id'] }}" class="guide-section card shadow-sm">
                                <div class="card-body p-4 p-md-5">
                                    <h2 class="guide-section-heading">
                                        <span class="guide-section-number">{{ $loop->iteration }}</span>
                                        {{ $section['heading'] }}
                                    </h2>
                                    <div class="guide-copy">
                                        @foreach($section['blocks'] as $block)
                                            @if($block['type'] === 'label')
                                                <div class="guide-copy-label">{{ $block['content'] }}</div>
                                            @elseif($block['type'] === 'ordered')
                                                <ol>
                                                    @foreach($block['items'] as $item)
                                                        <li>{{ $item }}</li>
                                                    @endforeach
                                                </ol>
                                            @elseif($block['type'] === 'unordered')
                                                <ul>
                                                    @foreach($block['items'] as $item)
                                                        <li>{{ $item }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p>{{ $block['content'] }}</p>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection

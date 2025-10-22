<x-filament-panels::page>
    {{-- 메인 컨텐츠: 좌우 2:1 레이아웃 --}}
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        {{-- 왼쪽: 사용자 정보 + TikTok 프로필 임베드 (1/2) --}}
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            {{-- 사용자 정보 카드 --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-content p-6">
                    <div class="flex items-center gap-4">
                        @if($record->profile_image)
                            <img src="{{ $record->profile_image }}"
                                 alt="{{ $record->username }}"
                                 class="w-16 h-16 rounded-full object-cover">
                        @endif
                        <div>
                            <h2 class="text-xl font-bold">{{ $record->username }}</h2>
                            <p class="text-gray-600 dark:text-gray-400">{{ $record->nickname }}</p>
                            <p class="text-sm text-gray-500">팔로워: {{ number_format($record->followers) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TikTok 프로필 임베드 --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex flex-col gap-3 p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                            TikTok 프로필
                        </h3>
                    </div>
                </div>
                <div class="fi-section-content p-6 pt-0">
                    @if($record->profile_url && $record->username)
                        <div class="space-y-6">
                            {{-- TikTok 공식 임베드 --}}
                            <div class="flex justify-center">
                                <div style="max-width: 100%; width: 100%;">
                                    <blockquote
                                        class="tiktok-embed"
                                        cite="{{ $record->profile_url }}"
                                        data-unique-id="{{ $record->username }}"
                                        data-embed-from="embed_page"
                                        data-embed-type="creator"
                                        style="max-width: 100%; min-width: 288px; overflow: auto;">
                                        <section>
                                            <a target="_blank" href="{{ $record->profile_url }}">{{ '@' . $record->username }}</a>
                                        </section>
                                    </blockquote>
                                </div>
                            </div>

                            {{-- 최근 동영상 목록 (있는 경우) --}}
                            @if($record->videos && $record->videos->count() > 0)
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white">최근 업로드 영상</h4>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">총 {{ $record->videos->count() }}개</span>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                    @foreach($record->videos->take(6) as $video)
                                    <a href="{{ $video->video_url ?? '#' }}"
                                       target="_blank"
                                       class="group relative aspect-[9/16] bg-gray-100 dark:bg-gray-800 rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-200 transform hover:scale-105">
                                        @if($video->cover_url)
                                        <img src="{{ $video->cover_url }}"
                                             alt="Video thumbnail"
                                             class="w-full h-full object-cover">
                                        @endif
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                            <div class="absolute bottom-0 left-0 right-0 p-3">
                                                <div class="flex items-center justify-center">
                                                    <div class="w-12 h-12 bg-white/90 rounded-full flex items-center justify-center">
                                                        <svg class="w-6 h-6 text-pink-500" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @if($video->views_count ?? false)
                                        <div class="absolute top-2 right-2 bg-black/60 text-white text-xs px-2 py-1 rounded-full">
                                            {{ number_format($video->views_count) }} 조회
                                        </div>
                                        @endif
                                    </a>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400">프로필 URL 또는 사용자명이 없습니다.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- 오른쪽: 심사 정보 + 버튼들 (1/2) --}}
        <div>
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="position: sticky; top: 1.5rem;">
                <div class="fi-section-header flex flex-col gap-3 p-6">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        심사 정보
                    </h3>
                </div>
                <div class="fi-section-content p-6 pt-0">
                    <div class="space-y-6">
                        <div>
                            <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                                <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                                    심사 점수
                                </span>
                            </label>
                            <div class="mt-2">
                                <input
                                    type="number"
                                    wire:model="data.review_score"
                                    min="0"
                                    max="100"
                                    placeholder="0-100점 사이로 입력"
                                    class="fi-input block w-full rounded-lg border-gray-300 bg-white shadow-sm ring-1 ring-gray-950/10 focus:border-primary-600 focus:ring-primary-600 dark:border-white/10 dark:bg-white/5 dark:ring-white/20 dark:focus:border-primary-500 dark:focus:ring-primary-500"
                                >
                                <p class="mt-1 text-xs text-gray-500">점</p>
                            </div>
                        </div>

                        <div>
                            <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                                <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                                    심사 코멘트
                                </span>
                            </label>
                            <div class="mt-2">
                                <textarea
                                    wire:model="data.review_comment"
                                    rows="8"
                                    placeholder="심사 내용을 입력하세요"
                                    class="fi-input block w-full rounded-lg border-gray-300 bg-white shadow-sm ring-1 ring-gray-950/10 focus:border-primary-600 focus:ring-primary-600 dark:border-white/10 dark:bg-white/5 dark:ring-white/20 dark:focus:border-primary-500 dark:focus:ring-primary-500"
                                ></textarea>
                            </div>
                        </div>

                        {{-- 액션 버튼 --}}
                        <div style="display: flex; gap: 0.5rem; padding-top: 1rem; border-top: 1px solid rgb(229 231 235 / 1);">
                            @if($previousRecord)
                                <x-filament::button
                                    color="gray"
                                    wire:click="goToPrevious"
                                    icon="heroicon-o-arrow-left"
                                    style="flex: 1;"
                                >
                                    이전
                                </x-filament::button>
                            @else
                                <div style="flex: 1;"></div>
                            @endif

                            <x-filament::button
                                color="success"
                                wire:click="approve"
                                icon="heroicon-o-check-circle"
                                wire:confirm="정말로 이 사용자를 승인하시겠습니까?"
                                style="flex: 1;"
                            >
                                승인
                            </x-filament::button>

                            <x-filament::button
                                color="danger"
                                wire:click="reject"
                                icon="heroicon-o-x-circle"
                                wire:confirm="정말로 이 사용자를 탈락 처리하시겠습니까?"
                                style="flex: 1;"
                            >
                                탈락
                            </x-filament::button>

                            @if($nextRecord)
                                <x-filament::button
                                    color="gray"
                                    wire:click="goToNext"
                                    icon="heroicon-o-arrow-right"
                                    icon-position="after"
                                    style="flex: 1;"
                                >
                                    다음
                                </x-filament::button>
                            @else
                                <div style="flex: 1;"></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TikTok 임베드 스크립트 --}}
    @push('scripts')
    <script async src="https://www.tiktok.com/embed.js"></script>
    @endpush
</x-filament-panels::page>

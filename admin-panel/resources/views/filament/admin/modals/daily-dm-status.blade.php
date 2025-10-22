<div class="space-y-4">
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
            <div class="text-sm text-blue-600 dark:text-blue-400 font-medium">총 발송 한도</div>
            <div class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">{{ $total }}건</div>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
            <div class="text-sm text-green-600 dark:text-green-400 font-medium">오늘 발송</div>
            <div class="text-2xl font-bold text-green-900 dark:text-green-100 mt-1">{{ $sent }}건</div>
        </div>
        <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg">
            <div class="text-sm text-orange-600 dark:text-orange-400 font-medium">남은 발송</div>
            <div class="text-2xl font-bold text-orange-900 dark:text-orange-100 mt-1">{{ $remaining }}건</div>
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
        <div class="flex items-center gap-2 mb-2">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="font-medium text-gray-700 dark:text-gray-300">안내</span>
        </div>
        <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 ml-7">
            <li>• 모든 국가 설정을 합쳐서 하루 최대 100건까지 발송됩니다.</li>
            <li>• 오늘 자정에 발송 카운트가 초기화됩니다.</li>
            <li>• 발송 성공한 건만 카운트에 포함됩니다.</li>
        </ul>
    </div>
</div>

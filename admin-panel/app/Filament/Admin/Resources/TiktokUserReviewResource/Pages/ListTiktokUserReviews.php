<?php

namespace App\Filament\Admin\Resources\TiktokUserReviewResource\Pages;

use App\Filament\Admin\Resources\TiktokUserReviewResource;
use App\Models\TiktokUser;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListTiktokUserReviews extends ListRecords
{
    protected static string $resource = TiktokUserReviewResource::class;

    public function mount(): void
    {
        parent::mount();

        // 첫 번째 pending 사용자를 찾아서 리다이렉트
        $firstPending = TiktokUser::where('review_status', TiktokUser::REVIEW_STATUS_PENDING)
            ->orderBy('id', 'asc')
            ->first();

        if ($firstPending) {
            $this->redirect(TiktokUserReviewResource::getUrl('review', ['record' => $firstPending->id]));
        } else {
            Notification::make()
                ->title('심사할 사용자가 없습니다')
                ->body('모든 사용자의 심사가 완료되었습니다.')
                ->success()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

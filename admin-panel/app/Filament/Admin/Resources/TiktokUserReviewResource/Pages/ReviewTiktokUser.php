<?php

namespace App\Filament\Admin\Resources\TiktokUserReviewResource\Pages;

use App\Filament\Admin\Resources\TiktokUserReviewResource;
use App\Models\TiktokUser;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ReviewTiktokUser extends ViewRecord
{
    protected static string $resource = TiktokUserReviewResource::class;

    protected static string $view = 'filament.admin.resources.tiktok-user-review-resource.pages.review-tiktok-user';

    public ?TiktokUser $nextRecord = null;
    public ?TiktokUser $previousRecord = null;

    public ?array $data = [];

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->form->fill([
            'review_score' => $this->record->review_score,
            'review_comment' => $this->record->review_comment,
        ]);

        // 다음/이전 레코드 찾기 (review_status가 pending인 것만)
        $this->nextRecord = TiktokUser::where('review_status', TiktokUser::REVIEW_STATUS_PENDING)
            ->where('id', '>', $this->record->id)
            ->orderBy('id', 'asc')
            ->first();

        $this->previousRecord = TiktokUser::where('review_status', TiktokUser::REVIEW_STATUS_PENDING)
            ->where('id', '<', $this->record->id)
            ->orderBy('id', 'desc')
            ->first();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('review_score')
                    ->label('심사 점수')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('점')
                    ->placeholder('0-100점 사이로 입력')
                    ->default(0),
                Textarea::make('review_comment')
                    ->label('심사 코멘트')
                    ->rows(4)
                    ->placeholder('심사 내용을 입력하세요')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function approve(): void
    {
        $data = $this->form->getState();

        $this->record->update([
            'review_status' => TiktokUser::REVIEW_STATUS_APPROVED,
            'review_score' => $data['review_score'] ?? 0,
            'review_comment' => $data['review_comment'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        Notification::make()
            ->title('승인 완료')
            ->body($this->record->username . ' 사용자를 승인했습니다.')
            ->success()
            ->send();

        $this->redirectToNext();
    }

    public function reject(): void
    {
        $data = $this->form->getState();

        $this->record->update([
            'review_status' => TiktokUser::REVIEW_STATUS_REJECTED,
            'review_score' => $data['review_score'] ?? 0,
            'review_comment' => $data['review_comment'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        Notification::make()
            ->title('탈락 처리 완료')
            ->body($this->record->username . ' 사용자를 탈락 처리했습니다.')
            ->warning()
            ->send();

        $this->redirectToNext();
    }

    public function goToNext(): void
    {
        if ($this->nextRecord) {
            $this->redirect(TiktokUserReviewResource::getUrl('review', ['record' => $this->nextRecord->id]));
        } else {
            Notification::make()
                ->title('마지막 사용자입니다')
                ->body('모든 사용자 심사를 완료했습니다.')
                ->success()
                ->send();

            $this->redirect('/admin');
        }
    }

    public function goToPrevious(): void
    {
        if ($this->previousRecord) {
            $this->redirect(TiktokUserReviewResource::getUrl('review', ['record' => $this->previousRecord->id]));
        } else {
            Notification::make()
                ->title('첫 번째 사용자입니다')
                ->info()
                ->send();
        }
    }

    protected function redirectToNext(): void
    {
        if ($this->nextRecord) {
            $this->redirect(TiktokUserReviewResource::getUrl('review', ['record' => $this->nextRecord->id]));
        } else {
            Notification::make()
                ->title('심사 완료')
                ->body('모든 사용자 심사를 완료했습니다.')
                ->success()
                ->send();

            $this->redirect('/admin');
        }
    }

    public function getBreadcrumb(): string
    {
        return '사용자 심사';
    }
}

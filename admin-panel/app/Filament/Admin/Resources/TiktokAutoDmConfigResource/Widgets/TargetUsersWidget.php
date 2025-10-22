<?php

namespace App\Filament\Admin\Resources\TiktokAutoDmConfigResource\Widgets;

use App\Models\TiktokUser;
use App\Models\TiktokAutoDmConfig;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TargetUsersWidget extends BaseWidget
{
    public ?TiktokAutoDmConfig $record = null;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'DM 발송 대상 사용자 목록';

    public function table(Table $table): Table
    {
        if (!$this->record) {
            return $table
                ->query(TiktokUser::query()->whereRaw('1 = 0'))
                ->emptyStateHeading('설정을 먼저 저장해주세요')
                ->emptyStateDescription('설정 저장 후 발송 대상 사용자 목록을 확인할 수 있습니다.');
        }

        $query = TiktokUser::query()
            ->where('review_status', TiktokUser::REVIEW_STATUS_APPROVED)
            ->where('status', TiktokUser::STATUS_UNCONFIRMED)
            ->where('review_score', '>=', $this->record->min_review_score ?? 0)
            ->whereNotNull('username');

        if ($this->record->country) {
            $query->where('country', $this->record->country);
        }

        $totalCount = $query->count();

        return $table
            ->query($query)
            ->heading("DM 발송 대상 사용자 목록 (총 {$totalCount}명)")
            ->description('심사 승인 + 미확인 상태 + 설정한 국가에 해당하는 사용자들입니다.')
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->label('사용자명')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-user'),
                Tables\Columns\TextColumn::make('nickname')
                    ->label('닉네임')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('country')
                    ->label('국가')
                    ->formatStateUsing(fn (?string $state): string =>
                        $state ? (TiktokAutoDmConfig::getCountryOptions()[$state] ?? $state) : '-'
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('review_score')
                    ->label('심사 점수')
                    ->suffix('점')
                    ->sortable()
                    ->color(fn ($state) => $state >= 80 ? 'success' : ($state >= 60 ? 'warning' : 'danger')),
                Tables\Columns\TextColumn::make('followers')
                    ->label('팔로워')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('진행상태')
                    ->formatStateUsing(fn (string $state): string => TiktokUser::getStatusLabels()[$state] ?? $state)
                    ->colors(TiktokUser::getStatusColors()),
                Tables\Columns\BadgeColumn::make('review_status')
                    ->label('심사상태')
                    ->formatStateUsing(fn (string $state): string => TiktokUser::getReviewStatusLabels()[$state] ?? $state)
                    ->colors(TiktokUser::getReviewStatusColors()),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('심사일시')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('high_score')
                    ->label('고득점 (80점 이상)')
                    ->query(fn (Builder $query): Builder => $query->where('review_score', '>=', 80)),
                Tables\Filters\Filter::make('many_followers')
                    ->label('팔로워 많음 (10만 이상)')
                    ->query(fn (Builder $query): Builder => $query->where('followers', '>=', 100000)),
            ])
            ->defaultSort('review_score', 'desc')
            ->paginated([10, 25, 50, 100])
            ->poll('30s') // 30초마다 자동 새로고침
            ->emptyStateHeading('발송 대상 사용자가 없습니다')
            ->emptyStateDescription('설정한 조건(국가, 최소 점수)에 맞는 승인된 미확인 사용자가 없습니다.');
    }
}

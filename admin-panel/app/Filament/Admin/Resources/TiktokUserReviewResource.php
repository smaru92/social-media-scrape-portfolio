<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TiktokUserReviewResource\Pages;
use App\Models\TiktokUser;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class TiktokUserReviewResource extends Resource
{
    protected static ?string $model = TiktokUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $label = '사용자 심사';
    protected static ?string $navigationGroup = '틱톡(Tiktok)';
    protected static ?string $navigationLabel = '사용자 심사';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('profile_image')
                    ->label('프로필')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png'))
                    ->width(40)
                    ->height(40)
                    ->getStateUsing(function ($record) {
                        if ($record->profile_image && !str_starts_with($record->profile_image, 'http')) {
                            return asset('storage/' . $record->profile_image);
                        }
                        return $record->profile_image;
                    }),
                TextColumn::make('username')
                    ->label('계정명')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nickname')
                    ->label('닉네임')
                    ->searchable(),
                TextColumn::make('followers')
                    ->label('팔로워')
                    ->numeric()
                    ->sortable(),
                BadgeColumn::make('review_status')
                    ->label('심사 상태')
                    ->formatStateUsing(fn ($state) => TiktokUser::getReviewStatusLabels()[$state] ?? '대기')
                    ->colors([
                        'warning' => TiktokUser::REVIEW_STATUS_PENDING,
                        'success' => TiktokUser::REVIEW_STATUS_APPROVED,
                        'danger' => TiktokUser::REVIEW_STATUS_REJECTED,
                    ])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('review_score')
                    ->label('심사 점수')
                    ->sortable(),
                TextColumn::make('reviewer.name')
                    ->label('심사자')
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label('심사 일시')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('review_status')
                    ->label('심사 상태')
                    ->options(TiktokUser::getReviewStatusLabels())
                    ->default(TiktokUser::REVIEW_STATUS_PENDING),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('review_status', TiktokUser::REVIEW_STATUS_PENDING);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiktokUserReviews::route('/'),
            'review' => Pages\ReviewTiktokUser::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

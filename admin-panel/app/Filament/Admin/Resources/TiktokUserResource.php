<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TiktokUserResource\Pages;
use App\Filament\Admin\Resources\TiktokUserResource\RelationManagers;
use App\Models\TiktokUser;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TiktokUserResource extends Resource
{
    protected static ?string $model = TiktokUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $label = '사용자';
    protected static ?string $navigationGroup = '틱톡(Tiktok)';
    protected static ?string $navigationLabel = '사용자';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('username')
                    ->label('계정명')
                    ->required()
                    ->maxLength(255),
                TextInput::make('keyword')
                    ->label('키워드')
                    ->maxLength(255),
                TextInput::make('nickname')
                    ->label('닉네임')
                    ->maxLength(255),
                TextInput::make('profile_image')
                    ->label('프로필 이미지 URL')
                    ->url()
                    ->placeholder('https://example.com/image.jpg')
                    ->columnSpanFull(),
                TextInput::make('followers')
                    ->label('팔로워')
                    ->numeric(),
                TextInput::make('profile_url')
                    ->label('프로필 URL')
                    ->maxLength(255),
                Textarea::make('bio')
                    ->label('자기소개')
                    ->columnSpanFull(),
                TextInput::make('memo')
                    ->label('메모')
                    ->maxLength(255),
                Toggle::make('is_collaborator')
                    ->label('협업자 여부')
                    ->default(false),
                DateTimePicker::make('collaborated_at')
                    ->label('협업 시작일')
                    ->visible(fn ($get) => $get('is_collaborator')),
                Select::make('status')
                    ->label('진행상태')
                    ->options(TiktokUser::getStatusLabels())
                    ->default(TiktokUser::STATUS_UNCONFIRMED)
                    ->required(),
                Select::make('country')
                    ->label('국가')
                    ->options(self::getCountryOptions())
                    ->searchable()
                    ->placeholder('국가 선택'),

                Forms\Components\Section::make('심사 정보')
                    ->schema([
                        Select::make('review_status')
                            ->label('심사 상태')
                            ->options(TiktokUser::getReviewStatusLabels())
                            ->default(TiktokUser::REVIEW_STATUS_PENDING)
                            ->required(),
                        TextInput::make('review_score')
                            ->label('심사 점수')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('점'),
                        Textarea::make('review_comment')
                            ->label('심사 코멘트')
                            ->rows(3)
                            ->columnSpanFull(),
                        DateTimePicker::make('reviewed_at')
                            ->label('심사 일시')
                            ->disabled(),
                        Select::make('reviewed_by')
                            ->label('심사자')
                            ->relationship('reviewer', 'name')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

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
                    ->sortable(),
                TextColumn::make('profile_url')
                    ->label('프로필 URL')
                    ->url(fn ($record) => $record->profile_url)
                    ->openUrlInNewTab()
                    ->limit(50),
                TextColumn::make('keyword')
                    ->label('키워드')
                    ->searchable(),
                BadgeColumn::make('status')
                    ->label('진행상태')
                    ->getStateUsing(fn ($record) => $record->status ?? 'unconfirmed')
                    ->formatStateUsing(fn ($state) => TiktokUser::getStatusLabels()[$state] ?? '미확인')
                    ->colors([
                        'gray' => TiktokUser::STATUS_UNCONFIRMED,
                        'info' => TiktokUser::STATUS_DM_SENT,
                        'warning' => TiktokUser::STATUS_DM_REPLIED,
                        'primary' => TiktokUser::STATUS_FORM_SUBMITTED,
                        'danger' => TiktokUser::STATUS_UPLOAD_WAITING,
                        'success' => TiktokUser::STATUS_UPLOAD_COMPLETED,
                    ])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country')
                    ->label('국가')
                    ->formatStateUsing(fn ($state) => self::getCountryOptions()[$state] ?? $state)
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_collaborator')
                    ->label('제안 수락')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
                TextColumn::make('collaborated_at')
                    ->label('협업시작일')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                BadgeColumn::make('review_status')
                    ->label('심사 상태')
                    ->formatStateUsing(fn ($state) => TiktokUser::getReviewStatusLabels()[$state] ?? '대기')
                    ->colors([
                        'warning' => TiktokUser::REVIEW_STATUS_PENDING,
                        'success' => TiktokUser::REVIEW_STATUS_APPROVED,
                        'danger' => TiktokUser::REVIEW_STATUS_REJECTED,
                    ])
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('review_score')
                    ->label('심사 점수')
                    ->suffix('점')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviewer.name')
                    ->label('심사자')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviewed_at')
                    ->label('심사 일시')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('생성일')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('최근수정일')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_collaborator')
                    ->label('협업자 여부')
                    ->placeholder('전체')
                    ->trueLabel('협업자')
                    ->falseLabel('일반 사용자')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_collaborator', true),
                        false: fn (Builder $query) => $query->where('is_collaborator', false),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('status')
                    ->label('진행상태')
                    ->options(TiktokUser::getStatusLabels())
                    ->placeholder('전체'),
                SelectFilter::make('country')
                    ->label('국가')
                    ->options(self::getCountryOptions())
                    ->searchable()
                    ->placeholder('전체 국가'),
                SelectFilter::make('review_status')
                    ->label('심사 상태')
                    ->options(TiktokUser::getReviewStatusLabels())
                    ->placeholder('전체'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('updateStatus')
                        ->label('상태 일괄 변경')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->form([
                            Select::make('status')
                                ->label('변경할 상태')
                                ->options(TiktokUser::getStatusLabels())
                                ->required(),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data): void {
                            $records->each(function ($record) use ($data) {
                                $record->update(['status' => $data['status']]);
                            });

                            Notification::make()
                                ->title('상태 변경 완료')
                                ->body($records->count() . '개의 사용자 상태가 변경되었습니다.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PersonalInfoRelationManager::class,
            RelationManagers\VideosRelationManager::class,
            RelationManagers\UploadRequestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiktokUsers::route('/'),
            'create' => Pages\CreateTiktokUser::route('/create'),
            'edit' => Pages\EditTiktokUser::route('/{record}/edit'),
        ];
    }

    public static function getCountryOptions(): array
    {
        return [
            'US' => '🇺🇸 미국',
            'GB' => '🇬🇧 영국',
            'JP' => '🇯🇵 일본',
            'KR' => '🇰🇷 한국',
            'CN' => '🇨🇳 중국',
            'DE' => '🇩🇪 독일',
            'FR' => '🇫🇷 프랑스',
            'IT' => '🇮🇹 이탈리아',
            'ES' => '🇪🇸 스페인',
            'CA' => '🇨🇦 캐나다',
            'AU' => '🇦🇺 호주',
            'BR' => '🇧🇷 브라질',
            'MX' => '🇲🇽 멕시코',
            'IN' => '🇮🇳 인도',
            'ID' => '🇮🇩 인도네시아',
            'TH' => '🇹🇭 태국',
            'VN' => '🇻🇳 베트남',
            'PH' => '🇵🇭 필리핀',
            'MY' => '🇲🇾 말레이시아',
            'SG' => '🇸🇬 싱가포르',
            'RU' => '🇷🇺 러시아',
            'TR' => '🇹🇷 터키',
            'AE' => '🇦🇪 아랍에미리트',
            'SA' => '🇸🇦 사우디아라비아',
            'AR' => '🇦🇷 아르헨티나',
            'CL' => '🇨🇱 칠레',
            'CO' => '🇨🇴 콜롬비아',
            'NL' => '🇳🇱 네덜란드',
            'BE' => '🇧🇪 벨기에',
            'CH' => '🇨🇭 스위스',
            'SE' => '🇸🇪 스웨덴',
            'NO' => '🇳🇴 노르웨이',
            'DK' => '🇩🇰 덴마크',
            'FI' => '🇫🇮 핀란드',
            'PL' => '🇵🇱 폴란드',
            'PT' => '🇵🇹 포르투갈',
            'GR' => '🇬🇷 그리스',
            'HK' => '🇭🇰 홍콩',
            'TW' => '🇹🇼 대만',
            'NZ' => '🇳🇿 뉴질랜드',
            'IE' => '🇮🇪 아일랜드',
            'AT' => '🇦🇹 오스트리아',
            'IL' => '🇮🇱 이스라엘',
            'EG' => '🇪🇬 이집트',
            'ZA' => '🇿🇦 남아프리카',
            'NG' => '🇳🇬 나이지리아',
        ];
    }
}

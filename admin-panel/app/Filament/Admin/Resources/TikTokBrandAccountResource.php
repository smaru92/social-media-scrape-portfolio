<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TikTokBrandAccountResource\Pages;
use App\Models\TikTokBrandAccount;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Admin\Resources\TiktokUserResource;

class TikTokBrandAccountResource extends Resource
{
    protected static ?string $model = TikTokBrandAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $label = '브랜드 계정';
    protected static ?string $navigationGroup = '틱톡(Tiktok)';
    protected static ?string $navigationLabel = '브랜드 계정';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('username')
                    ->label('계정명')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('brand_name')
                    ->label('브랜드명')
                    ->required()
                    ->maxLength(255),
                Select::make('country')
                    ->label('국가')
                    ->options(TiktokUserResource::getCountryOptions())
                    ->searchable()
                    ->placeholder('국가 선택'),
                TextInput::make('category')
                    ->label('카테고리')
                    ->maxLength(255),
                TextInput::make('nickname')
                    ->label('표시 이름')
                    ->maxLength(255),
                TextInput::make('followers')
                    ->label('팔로워 수')
                    ->numeric()
                    ->default(0),
                TextInput::make('following_count')
                    ->label('팔로잉 수')
                    ->numeric()
                    ->default(0),
                TextInput::make('video_count')
                    ->label('비디오 수')
                    ->numeric()
                    ->default(0),
                TextInput::make('profile_url')
                    ->label('프로필 URL')
                    ->url()
                    ->columnSpanFull(),
                TextInput::make('profile_image')
                    ->label('프로필 이미지')
                    ->columnSpanFull(),
                Textarea::make('bio')
                    ->label('계정 소개')
                    ->columnSpanFull(),
                Toggle::make('is_verified')
                    ->label('공식 인증 여부')
                    ->default(false),
                DateTimePicker::make('last_scraped_at')
                    ->label('마지막 스크랩 시간'),
                Select::make('status')
                    ->label('계정 상태')
                    ->options(TikTokBrandAccount::getStatusLabels())
                    ->default(TikTokBrandAccount::STATUS_ACTIVE)
                    ->required(),
                TextInput::make('memo')
                    ->label('비고')
                    ->maxLength(255),
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
                TextColumn::make('brand_name')
                    ->label('브랜드명')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country')
                    ->label('국가')
                    ->formatStateUsing(fn ($state) => TiktokUserResource::getCountryOptions()[$state] ?? $state)
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('카테고리')
                    ->searchable(),
                TextColumn::make('followers')
                    ->label('팔로워')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_verified')
                    ->label('인증')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                BadgeColumn::make('status')
                    ->label('상태')
                    ->formatStateUsing(fn ($state) => TikTokBrandAccount::getStatusLabels()[$state] ?? '알 수 없음')
                    ->colors([
                        'success' => TikTokBrandAccount::STATUS_ACTIVE,
                        'danger' => TikTokBrandAccount::STATUS_INACTIVE,
                    ]),
                TextColumn::make('last_scraped_at')
                    ->label('마지막 스크랩')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('생성일')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('계정 상태')
                    ->options(TikTokBrandAccount::getStatusLabels())
                    ->placeholder('전체'),
                SelectFilter::make('country')
                    ->label('국가')
                    ->options(TiktokUserResource::getCountryOptions())
                    ->searchable()
                    ->placeholder('전체 국가'),
                SelectFilter::make('is_verified')
                    ->label('공식 인증')
                    ->options([
                        '1' => '인증됨',
                        '0' => '미인증'
                    ])
                    ->placeholder('전체'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('scrapeRepostVideos')
                        ->label('리포스트 영상 스크랩')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('리포스트 영상 스크랩')
                        ->modalDescription('선택한 브랜드 계정들의 리포스트 영상을 스크랩하시겠습니까?')
                        ->modalSubmitActionLabel('스크랩 시작')
                        ->action(function (Collection $records) {
                            try {
                                $usernames = $records->pluck('username')->toArray();

                                if (empty($usernames)) {
                                    Notification::make()
                                        ->title('실패')
                                        ->body('선택된 계정이 없습니다.')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                $apiUrl = config('app.api_url') . '/api/v1/tiktok/scrape_repost_video';

                                // API 요청 전송 (타임아웃 발생해도 무시)
                                try {
                                    Http::timeout(3)->post($apiUrl, [
                                        'usernames' => $usernames
                                    ]);
                                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                                    // 타임아웃 발생 시 무시 (백그라운드 처리 중)
                                }

                                Notification::make()
                                    ->title('요청 전송 완료')
                                    ->body(count($usernames) . '개의 계정에 대한 리포스트 영상 스크랩 요청을 전송했습니다. 백그라운드에서 처리됩니다.')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('오류')
                                    ->body('스크랩 중 오류가 발생했습니다: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTikTokBrandAccounts::route('/'),
            'create' => Pages\CreateTikTokBrandAccount::route('/create'),
            'edit' => Pages\EditTikTokBrandAccount::route('/{record}/edit'),
        ];
    }
}

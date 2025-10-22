<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TiktokUploadRequestResource\Pages;
use App\Models\TiktokUploadRequest;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;

class TiktokUploadRequestResource extends Resource
{
    protected static ?string $model = TiktokUploadRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static ?string $label = '업로드 요청';
    protected static ?string $navigationGroup = '틱톡(Tiktok) - 리뷰';
    protected static ?string $navigationLabel = '업로드 요청';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('tiktok_user_id')
                    ->label('사용자')
                    ->relationship('tiktokUser', 'username', function ($query) {
                        return $query->orderBy('nickname', 'asc');
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->username . ($record->nickname ? " ({$record->nickname})" : ''))
                    ->required()
                    ->searchable()
                    ->preload(),
                Textarea::make('request_content')
                    ->label('요청사항')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                TagsInput::make('request_tags')
                    ->label('필수 해시태그/멘션')
                    ->placeholder('#해시태그 @멘션 등을 입력')
                    ->helperText('공백으로 구분하여 여러 개를 입력할 수 있습니다.')
                    ->separator(' ')
                    ->columnSpanFull(),
                DateTimePicker::make('requested_at')
                    ->label('요청일시')
                    ->required()
                    ->default(now()),
                Forms\Components\DatePicker::make('deadline_date')
                    ->label('게시 기한')
                    ->displayFormat('Y-m-d'),
                Toggle::make('is_uploaded')
                    ->label('업로드 여부')
                    ->default(false)
                    ->reactive(),
                Toggle::make('is_confirm')
                    ->label('최종 확인')
                    ->default(false)
                    ->helperText('담당자 최종 확인여부'),
                TextInput::make('upload_url')
                    ->label('업로드 URL')
                    ->url()
                    ->maxLength(255)
                    ->visible(fn ($get) => $get('is_uploaded')),
                TextInput::make('upload_thumbnail_url')
                    ->label('업로드 썸네일 URL')
                    ->url()
                    ->maxLength(255)
                    ->visible(fn ($get) => $get('is_uploaded')),
                DateTimePicker::make('uploaded_at')
                    ->label('업로드 일시')
                    ->visible(fn ($get) => $get('is_uploaded')),
                Select::make('tiktok_video_id')
                    ->label('연결된 동영상')
                    ->relationship('tiktokVideo', 'title')
                    ->searchable()
                    ->preload()
                    ->visible(fn ($get) => $get('is_uploaded')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('tiktokUser.username')
                    ->label('사용자')
                    ->getStateUsing(function ($record) {
                        if (!$record || !$record->tiktokUser) {
                            return '-';
                        }
                        return $record->tiktokUser->username . ($record->tiktokUser->nickname ? " ({$record->tiktokUser->nickname})" : '');
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('request_content')
                    ->label('요청사항')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('request_tags')
                    ->label('필수 태그')
                    ->badge()
                    ->separator(' ')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('requested_at')
                    ->label('요청일시')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('deadline_date')
                    ->label('게시 기한')
                    ->date('Y-m-d')
                    ->sortable()
                    ->color(function ($state, $record) {
                        if (!$record || !$state) {
                            return 'gray';
                        }
                        if (!$record->is_uploaded && $record->deadline_date->isPast()) {
                            return 'danger';
                        }
                        if (!$record->is_uploaded && $record->deadline_date->diffInDays(now()) <= 3) {
                            return 'warning';
                        }
                        return 'gray';
                    }),
                IconColumn::make('is_uploaded')
                    ->label('업로드')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->sortable(),
                IconColumn::make('is_confirm')
                    ->label('담당자 확인')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),
                ImageColumn::make('upload_thumbnail_url')
                    ->label('썸네일')
                    ->height(60)
                    ->width(80)
                    ->visible(function ($record) {
                        return $record && $record->is_uploaded;
                    }),
                TextColumn::make('uploaded_at')
                    ->label('업로드일')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('upload_url')
                    ->label('동영상 링크')
                    ->url(function ($state) {
                        return $state;
                    })
                    ->openUrlInNewTab()
                    ->formatStateUsing(function ($state) {
                        return $state ? '보기' : '-';
                    })
                    ->color(function ($state) {
                        return $state ? 'primary' : 'gray';
                    }),
            ])
            ->filters([
                TernaryFilter::make('is_uploaded')
                    ->label('업로드 상태')
                    ->placeholder('전체')
                    ->trueLabel('업로드 완료')
                    ->falseLabel('대기중')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_uploaded', true),
                        false: fn (Builder $query) => $query->where('is_uploaded', false),
                        blank: fn (Builder $query) => $query,
                    ),
                TernaryFilter::make('is_confirm')
                    ->label('확인 상태')
                    ->placeholder('전체')
                    ->trueLabel('확인 완료')
                    ->falseLabel('미확인')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_confirm', true),
                        false: fn (Builder $query) => $query->where('is_confirm', false),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('confirmSelected')
                        ->label('선택 항목 확인 처리')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = $records->count();

                            foreach ($records as $record) {
                                $record->update(['is_confirm' => true]);
                            }

                            Notification::make()
                                ->success()
                                ->title('확인 처리 완료')
                                ->body($count . '개 항목이 확인 처리되었습니다.')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('unconfirmSelected')
                        ->label('선택 항목 확인 취소')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = $records->count();

                            foreach ($records as $record) {
                                $record->update(['is_confirm' => false]);
                            }

                            Notification::make()
                                ->success()
                                ->title('확인 취소 완료')
                                ->body($count . '개 항목이 확인 취소되었습니다.')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('scrapeVideos')
                        ->label('선택 사용자 비디오 스크랩')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('비디오 스크랩 실행')
                        ->modalDescription(function (Collection $records) {
                            $usernames = $records->load('tiktokUser')
                                ->filter(function ($record) {
                                    return !$record->is_uploaded && // 업로드 안된 것만
                                           (!$record->deadline_date || $record->deadline_date->isFuture()); // 기한 안 지난 것만
                                })
                                ->pluck('tiktokUser.username')
                                ->filter()
                                ->unique()
                                ->values()
                                ->toArray();

                            $count = count($usernames);
                            $usernameList = implode(', ', array_slice($usernames, 0, 5));
                            if ($count > 5) {
                                $usernameList .= ' 외 ' . ($count - 5) . '명';
                            }

                            return "다음 {$count}명의 사용자 비디오를 스크랩합니다: {$usernameList}";
                        })
                        ->action(function (Collection $records) {
                            $usernames = $records->load('tiktokUser')
                                ->filter(function ($record) {
                                    return !$record->is_uploaded && // 업로드 안된 것만
                                           (!$record->deadline_date || $record->deadline_date->isFuture()); // 기한 안 지난 것만
                                })
                                ->pluck('tiktokUser.username')
                                ->filter()
                                ->unique()
                                ->values()
                                ->toArray();

                            if (empty($usernames)) {
                                Notification::make()
                                    ->warning()
                                    ->title('사용자 정보 없음')
                                    ->body('선택된 요청에 유효한 사용자가 없습니다.')
                                    ->send();
                                return;
                            }

                            try {
                                $apiUrl = rtrim(config('app.api_url'), '/') . '/api/v1/tiktok/scrape_video';

                                Http::post($apiUrl, [
                                    'usernames' => $usernames
                                ]);

                                Notification::make()
                                    ->success()
                                    ->title('비디오 스크랩 요청 완료')
                                    ->body(count($usernames) . '명의 사용자에 대한 비디오 스크랩이 백그라운드에서 진행됩니다.')
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('오류 발생')
                                    ->body('비디오 스크랩 중 오류가 발생했습니다: ' . $e->getMessage())
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
            'index' => Pages\ListTiktokUploadRequests::route('/'),
            'create' => Pages\CreateTiktokUploadRequest::route('/create'),
            'edit' => Pages\EditTiktokUploadRequest::route('/{record}/edit'),
        ];
    }
}

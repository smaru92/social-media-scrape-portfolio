<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TiktokUserPersonalInfoResource\Pages;
use App\Filament\Admin\Resources\TiktokUserPersonalInfoResource\RelationManagers;
use App\Models\TiktokUserPersonalInfo;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use App\Models\TiktokUploadRequest;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TiktokUserPersonalInfoResource extends Resource
{
    protected static ?string $model = TiktokUserPersonalInfo::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $label = '개인정보';
    protected static ?string $navigationGroup = '틱톡(Tiktok) - 리뷰';
    protected static ?string $navigationLabel = '개인정보';
    protected static ?int $navigationSort = 2;

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
                TextInput::make('name')
                    ->label('이름 (한자/한글)')
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('이메일')
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('전화번호')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('address')
                    ->label('주소')
                    ->maxLength(500),
                TextInput::make('postal_code')
                    ->label('우편번호')
                    ->maxLength(20),
                TextInput::make('category1')
                    ->label('분류1')
                    ->maxLength(255),
                TextInput::make('category2')
                    ->label('분류2')
                    ->maxLength(255),
                Textarea::make('brand_feedback')
                    ->label('브랜드 피드백')
                    ->rows(3)
                    ->columnSpanFull(),
                Radio::make('repost_permission')
                    ->label('리포스트 허가')
                    ->options([
                        'Y' => '예',
                        'N' => '아니오',
                        'P' => '대기중'
                    ])
                    ->default('pending'),
                DateTimePicker::make('form_submitted_at')
                    ->label('폼 제출 시간')
                    ->displayFormat('Y-m-d H:i:s'),
                Textarea::make('additional_info')
                    ->label('추가 정보')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->description('틱톡 사용자의 개인정보가 기록됩니다. (구글폼과 연동시 연동데이터가 자동으로 기록됩니다.)')
            ->columns([
                TextColumn::make('tiktokUser.username')
                    ->label('사용자')
                    ->formatStateUsing(fn ($record) => $record->tiktokUser->username . ($record->tiktokUser->nickname ? " ({$record->tiktokUser->nickname})" : ''))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category1')
                    ->label('분류1')
                    ->searchable(),
                TextColumn::make('category2')
                    ->label('분류2')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('이름')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('이메일')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('전화번호')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('주소')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('postal_code')
                    ->label('우편번호')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('repost_permission')
                    ->label('리포스트 허가')
                    ->badge()
                    ->colors([
                        'success' => 'Y',
                        'danger' => 'N',
                        'warning' => 'P'
                    ])
                    ->formatStateUsing(fn ($state) => match($state) {
                        'Y' => '허가',
                        'N' => '불허',
                        'P' => '대기중',
                        default => '-'
                    }),
                TextColumn::make('form_submitted_at')
                    ->label('폼 제출일')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('생성일')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category1')
                    ->label('분류1')
                    ->options(fn () => TiktokUserPersonalInfo::query()
                        ->whereNotNull('category1')
                        ->where('category1', '!=', '')
                        ->distinct()
                        ->pluck('category1', 'category1')
                        ->toArray()
                    )
                    ->searchable(),
                Tables\Filters\SelectFilter::make('category2')
                    ->label('분류2')
                    ->options(fn () => TiktokUserPersonalInfo::query()
                        ->whereNotNull('category2')
                        ->where('category2', '!=', '')
                        ->distinct()
                        ->pluck('category2', 'category2')
                        ->toArray()
                    )
                    ->searchable(),
                Tables\Filters\SelectFilter::make('repost_permission')
                    ->label('리포스트 허가')
                    ->options([
                        'Y' => '허가',
                        'N' => '불허',
                        'P' => '대기중'
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('createUploadRequests')
                        ->label('선택 사용자 업로드 요청 생성')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('success')
                        ->form([
                            Forms\Components\Textarea::make('request_content')
                                ->label('요청사항')
                                ->required()
                                ->rows(4)
                                ->placeholder('예: 제품 리뷰 영상을 만들어 주세요')
                                ->columnSpanFull(),
                            Forms\Components\TagsInput::make('request_tags')
                                ->label('필수 해시태그/멘션')
                                ->placeholder('#해시태그 @멘션 등을 입력')
                                ->helperText('공백으로 구분하여 여러 개를 입력할 수 있습니다.')
                                ->separator(' ')
                                ->columnSpanFull(),
                            Forms\Components\DatePicker::make('deadline_date')
                                ->label('게시 기한')
                                ->displayFormat('Y-m-d')
                                ->minDate(now())
                                ->default(now()->addDays(7)),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->tiktok_user_id) {
                                    TiktokUploadRequest::create([
                                        'tiktok_user_id' => $record->tiktok_user_id,
                                        'request_content' => $data['request_content'],
                                        'request_tags' => $data['request_tags'] ?? [],
                                        'deadline_date' => $data['deadline_date'],
                                        'requested_at' => now(),
                                        'is_uploaded' => false,
                                        'is_confirm' => false,
                                    ]);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('업로드 요청 생성 완료')
                                ->body($count . '개의 업로드 요청이 생성되었습니다.')
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
            RelationManagers\VideosRelationManager::class,
            RelationManagers\UploadRequestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiktokUserPersonalInfos::route('/'),
            'create' => Pages\CreateTiktokUserPersonalInfo::route('/create'),
            'edit' => Pages\EditTiktokUserPersonalInfo::route('/{record}/edit'),
        ];
    }
}

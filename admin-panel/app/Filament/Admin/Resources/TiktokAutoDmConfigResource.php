<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TiktokAutoDmConfigResource\Pages;
use App\Models\TiktokAutoDmConfig;
use App\Models\TiktokSender;
use App\Models\TiktokMessageTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TiktokAutoDmConfigResource extends Resource
{
    protected static ?string $model = TiktokAutoDmConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = '틱톡(Tiktok) - DM전송';

    protected static ?string $navigationLabel = '자동 DM 설정';

    protected static ?string $modelLabel = '자동 DM 설정';

    protected static ?string $pluralModelLabel = '자동 DM 설정';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('기본 설정')
                    ->description('국가별 자동 DM 설정을 관리합니다.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('설정 이름')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('예: 한국 사용자 자동 DM')
                            ->helperText('이 설정을 구분하기 위한 이름입니다.')
                            ->columnSpan(1),
                        Forms\Components\Select::make('country')
                            ->label('대상 국가')
                            ->options(TiktokAutoDmConfig::getCountryOptions())
                            ->required()
                            ->searchable()
                            ->placeholder('국가를 선택하세요')
                            ->helperText('선택한 국가의 사용자에게만 DM이 발송됩니다.')
                            ->columnSpan(1),
                        Forms\Components\Toggle::make('is_active')
                            ->label('자동 발송 활성화')
                            ->default(false)
                            ->inline(false)
                            ->helperText('활성화하면 설정된 시간에 심사 승인된 사용자에게 자동으로 DM이 발송됩니다.')
                            ->columnSpan(2),
                    ])->columns(2),

                Forms\Components\Section::make('발송 설정')
                    ->schema([
                        Forms\Components\Select::make('tiktok_sender_id')
                            ->label('발신 계정')
                            ->options(TiktokSender::pluck('nickname', 'id'))
                            ->searchable()
                            ->required()
                            ->placeholder('발신 계정을 선택하세요'),
                        Forms\Components\Select::make('tiktok_message_template_id')
                            ->label('메시지 템플릿')
                            ->options(TiktokMessageTemplate::pluck('title', 'id'))
                            ->searchable()
                            ->required()
                            ->placeholder('메시지 템플릿을 선택하세요'),
                    ])->columns(2),

                Forms\Components\Section::make('스케줄 설정')
                    ->schema([
                        Forms\Components\Select::make('schedule_type')
                            ->label('발송 주기')
                            ->options(TiktokAutoDmConfig::getScheduleTypeLabels())
                            ->default(TiktokAutoDmConfig::SCHEDULE_DAILY)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('schedule_day', null)),
                        Forms\Components\TimePicker::make('schedule_time')
                            ->label('발송 시간')
                            ->required()
                            ->default('09:00:00')
                            ->seconds(false)
                            ->format('H:i'),
                        Forms\Components\Select::make('schedule_day')
                            ->label(function (Forms\Get $get) {
                                $type = $get('schedule_type');
                                if ($type === TiktokAutoDmConfig::SCHEDULE_WEEKLY) {
                                    return '발송 요일';
                                } elseif ($type === TiktokAutoDmConfig::SCHEDULE_MONTHLY) {
                                    return '발송 일';
                                }
                                return '발송 날짜';
                            })
                            ->options(function (Forms\Get $get) {
                                $type = $get('schedule_type');
                                if ($type === TiktokAutoDmConfig::SCHEDULE_WEEKLY) {
                                    return [
                                        0 => '일요일',
                                        1 => '월요일',
                                        2 => '화요일',
                                        3 => '수요일',
                                        4 => '목요일',
                                        5 => '금요일',
                                        6 => '토요일',
                                    ];
                                } elseif ($type === TiktokAutoDmConfig::SCHEDULE_MONTHLY) {
                                    return collect(range(1, 31))->mapWithKeys(fn ($day) => [$day => $day . '일'])->toArray();
                                }
                                return [];
                            })
                            ->visible(fn (Forms\Get $get) => in_array($get('schedule_type'), [
                                TiktokAutoDmConfig::SCHEDULE_WEEKLY,
                                TiktokAutoDmConfig::SCHEDULE_MONTHLY
                            ]))
                            ->required(fn (Forms\Get $get) => in_array($get('schedule_type'), [
                                TiktokAutoDmConfig::SCHEDULE_WEEKLY,
                                TiktokAutoDmConfig::SCHEDULE_MONTHLY
                            ])),
                    ])->columns(3),

                Forms\Components\Section::make('발송 대상')
                    ->description('심사 승인된 틱톡 사용자 중 아래 조건을 만족하는 사용자에게 발송됩니다.')
                    ->schema([
                        Forms\Components\Placeholder::make('target_info')
                            ->label('발송 대상')
                            ->content('심사 승인(review_status = approved) + 미확인 상태(status = unconfirmed) + 설정한 국가')
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('daily_limit_info')
                            ->label('⚠️ 하루 발송 제한')
                            ->content('모든 국가 설정을 합쳐서 하루 최대 100건까지만 발송됩니다.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('min_review_score')
                            ->label('최소 심사 점수')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->suffix('점')
                            ->helperText('이 점수 이상인 사용자에게만 발송됩니다. (0점 = 모든 승인된 사용자)'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('설정 이름')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country')
                    ->label('국가')
                    ->formatStateUsing(fn (string $state): string => TiktokAutoDmConfig::getCountryOptions()[$state] ?? $state)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('활성화')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sender.nickname')
                    ->label('발신 계정')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('messageTemplate.title')
                    ->label('메시지 템플릿')
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('schedule_type')
                    ->label('발송 주기')
                    ->formatStateUsing(fn (string $state): string => TiktokAutoDmConfig::getScheduleTypeLabels()[$state] ?? $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('schedule_time')
                    ->label('발송 시간')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_review_score')
                    ->label('최소 점수')
                    ->suffix('점')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_sent_at')
                    ->label('마지막 발송')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('없음'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('생성일')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('country')
                    ->label('국가')
                    ->options(TiktokAutoDmConfig::getCountryOptions()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('활성화 상태')
                    ->placeholder('전체')
                    ->trueLabel('활성화')
                    ->falseLabel('비활성화'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTiktokAutoDmConfigs::route('/'),
            'create' => Pages\CreateTiktokAutoDmConfig::route('/create'),
            'edit' => Pages\EditTiktokAutoDmConfig::route('/{record}/edit'),
        ];
    }
}

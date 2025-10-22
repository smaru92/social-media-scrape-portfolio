import os
import json
import random
import time
import asyncio
from contextlib import contextmanager
from typing import Dict, List, Optional, Tuple
from pathlib import Path
from datetime import datetime, timedelta
from sqlalchemy.orm import Session
from app.models.tiktok import TikTokUserRepository, TikTokUserLog, TikTokMessageLog, TikTokMessage, TikTokUser, TikTokVideo, TikTokUploadRequest, TikTokBrandAccount, TikTokRepostVideo
from app.core.config import settings
from app.services.browser_manager import AsyncBrowserManager, SyncBrowserManager, TikTokBrowserConfig
from app.services.tiktok_utils import (
    TikTokDataParser, TikTokWaitUtils, TikTokImageUtils, 
    TikTokDatabaseUtils, TikTokValidationUtils, TikTokUrlUtils
)
from app.services.tiktok_message_handler import (
    TikTokMessageTemplateManager, TikTokMessageCounter, 
    TikTokMessageLogger, TikTokMessageProcessor
)
from app.services.tiktok_db_handler import TikTokDatabaseHandler
from app.services.tiktok_exceptions import (
    TikTokServiceException, TikTokBrowserException, TikTokCaptchaException,
    TikTokUserNotFoundException, TikTokLoginRequiredException, TikTokSessionExpiredException,
    TikTokScrapingException, TikTokMessageException, TikTokDatabaseException,
    safe_execute, handle_tiktok_exception
)
import paramiko
from dotenv import load_dotenv
import re
import requests
import hashlib
from urllib.parse import urlparse

load_dotenv()

class TikTokService:
    """TikTok 데이터 수집 및 처리를 위한 서비스 클래스 (Windows 호환)"""
    
    # === INITIALIZATION ===
    def __init__(self, db_session: Optional[Session] = None):
        self.db_session = db_session
        
        # 메시지 템플릿 매니저 초기화
        self.template_manager = TikTokMessageTemplateManager()
        
        # 데이터베이스 핸들러 초기화
        self.db_handler = TikTokDatabaseHandler(db_session) if db_session else None
        
        # 이미지 저장 디렉토리 설정
        self.image_base_dir = Path("tiktok_images")
        self.image_base_dir.mkdir(exist_ok=True)

    # === USER MANAGEMENT & SCRAPING ===
    def scrape_users(self, keyword: str, min_followers: int = 10000, scrolls: int = 5, save_to_db: bool = True, tiktok_user_log_id: int = None) -> Dict:
        """TikTok 사용자 검색 및 데이터 수집
        
        Args:
            keyword: 검색 키워드
            min_followers: 최소 팔로워 수
            scrolls: 스크롤 횟수
            save_to_db: 데이터베이스 저장 여부
            tiktok_user_log_id: TikTok 사용자 로그 ID
            
        Returns:
            수집된 사용자 데이터와 통계
        """
        
        async def _scrape_users_async():
            """내부 비동기 사용자 스크래핑 함수"""
            results = {
                'data': [],
                'search_user_count': 0,
                'save_user_count': 0,
                'db_stats': None
            }

            try:
                async with AsyncBrowserManager() as browser_manager:
                    # 브라우저 초기화 (비로그인 상태로 검색, 헤드리스 모드)
                    await browser_manager.initialize(headless=False, session_file=None)
                    print("⚠️ 비로그인 상태로 검색을 실행합니다. (헤드리스 모드)")
                    
                    # TikTok 메인 페이지로 이동하여 세션 활성화
                    await browser_manager.navigate_to_main_page()
                    
                    page = browser_manager.page
        
                    # TikTok 검색 페이지로 이동
                    print(f"🔍 '{keyword}' 검색을 시작합니다...")
                    await page.goto(f"https://www.tiktok.com/search/user?q={keyword}", wait_until="load")
                    await page.wait_for_timeout(5000)
        
                    # 자동 스크롤
                    await browser_manager.auto_scroll_async(scrolls=scrolls)
        
                    # 사용자 데이터 수집 - 다양한 셀렉터 시도
                    users = await page.query_selector_all('div[data-e2e="search-user-container"]')

                    # 대체 셀렉터 시도
                    if len(users) == 0:
                        print("⚠️ search-user-container를 찾을 수 없음. 대체 셀렉터 시도...")
                        users = await page.query_selector_all('div[data-e2e="search-user-item"]')

                    if len(users) == 0:
                        print("⚠️ search-user-item도 찾을 수 없음. 다른 셀렉터 시도...")
                        users = await page.query_selector_all('div[class*="UserItemContainer"]')

                    if len(users) == 0:
                        print("⚠️ UserItemContainer도 찾을 수 없음. a 태그로 시도...")
                        users = await page.query_selector_all('a[href*="/@"][class*="StyledLink"]')

                    results['search_user_count'] = len(users)

                    print(f"🔍 감지된 사용자: {results['search_user_count']}명", flush=True)

                    # 페이지 스크린샷 저장 (디버깅용)
                    if results['search_user_count'] == 0:
                        await page.screenshot(path=f'debug_search_{keyword}_no_results.png')
                        print(f"📸 디버그 스크린샷 저장: debug_search_{keyword}_no_results.png")
        
                    for block in users:
                        user_data = await self._extract_user_data_async(block, keyword)
                        if user_data and user_data['followers'] >= min_followers:
                            # 중복 체크
                            if not any(d.get("username") == user_data['username'] for d in results['data']):
                                results['data'].append(user_data)
        
                                # 즉시 DB에 저장 (한 건씩)
                                if save_to_db and self.db_session:
                                    print(f"사용자 저장시도 : {user_data['username']}", flush=True)
                                    save_result = self._save_single_user(user_data)
                                    if save_result.get('created') == 1:
                                        results['save_user_count'] += 1
                                        print(f"✔ {user_data['username']} ({user_data['followers']:,}) - 저장 완료", flush=True)
                                    elif save_result.get('updated') == 1:
                                        print(f"↻ {user_data['username']} ({user_data['followers']:,}) - 업데이트 완료", flush=True)
                                    else:
                                        print(f"⬬ {user_data['username']} ({user_data['followers']:,}) - 스킵", flush=True)
                                else:
                                    results['save_user_count'] += 1
                                    print(f"✔ {user_data['username']} ({user_data['followers']:,})", flush=True)
        
                        await page.wait_for_timeout(random.uniform(1000, 2000))
                    
                    print("\n" + "=" * 60)
                    print("✅ 사용자 스크래핑 완료!")
                    print("=" * 60)
                    
                    return results
        
            except Exception as e:
                print(f"❗스크래핑 오류: {e}")
                import traceback
                traceback.print_exc()
                results['error'] = str(e)
                
                # 에러 발생 시 로그 업데이트
                if tiktok_user_log_id and self.db_session:
                    self._update_user_log(tiktok_user_log_id, {
                        'search_user_count': results['search_user_count'],
                        'save_user_count': results['save_user_count'],
                        'is_error': True
                    })
                    
                return results
        
        # 비동기 함수 실행
        result = asyncio.run(_scrape_users_async())
        
        # 최종 통계 (이미 개별 저장했으므로 통계만 반환)
        if save_to_db and self.db_session:
            result['db_stats'] = {
                'created': result['save_user_count'],
                'message': '데이터가 실시간으로 저장되었습니다.'
            }
            
            # 성공적으로 완료된 경우 로그 업데이트
            if tiktok_user_log_id and not result.get('error'):
                self._update_user_log(tiktok_user_log_id, {
                    'search_user_count': result['search_user_count'],
                    'save_user_count': result['save_user_count'],
                    'is_error': False
                })

        return result

    async def _extract_user_data_async(self, block, keyword: str) -> Optional[Dict]:
        """
        사용자 블록에서 데이터를 추출합니다 (async 버전)

        Args:
            block: 사용자 정보를 포함한 HTML 블록
            keyword: 검색 키워드

        Returns:
            사용자 데이터 딕셔너리 또는 None
        """
        try:
            # 사용자명 추출 - 다양한 셀렉터 시도
            username_elem = await block.query_selector('p[data-e2e="search-user-unique-id"]')
            if not username_elem:
                # 대체 셀렉터 시도
                username_elem = await block.query_selector('h3[data-e2e="search-user-unique-id"]')
            if not username_elem:
                # @로 시작하는 텍스트 찾기
                username_elem = await block.query_selector('p:has-text("@"), span:has-text("@")')
            if not username_elem:
                print("⚠️ 사용자명을 찾을 수 없음")
                return None

            username = await username_elem.inner_text()
            username = username.replace('@', '').strip()  # @ 기호 제거

            # 닉네임 추출
            nickname_elem = await block.query_selector('p[data-e2e="search-user-nickname"]')
            if not nickname_elem:
                nickname_elem = await block.query_selector('h4[data-e2e="search-user-nickname"]')
            nickname = await nickname_elem.inner_text() if nickname_elem else username

            # 팔로워 수 추출 - search-follow-count 셀렉터 우선 시도
            followers_elem = await block.query_selector('span[data-e2e="search-follow-count"]')
            if not followers_elem:
                followers_elem = await block.query_selector('strong[data-e2e="search-follow-count"]')
            if not followers_elem:
                # 이전 셀렉터들도 시도
                followers_elem = await block.query_selector('strong[data-e2e="search-user-count"]')
            if not followers_elem:
                followers_elem = await block.query_selector('span[data-e2e="search-user-count"]')
            if not followers_elem:
                # "팔로워" 텍스트를 포함하는 요소 찾기
                followers_elem = await block.query_selector('span:has-text("팔로워"), strong:has-text("팔로워")')
            if not followers_elem:
                print(f"⚠️ {username}의 팔로워 수를 찾을 수 없음")
                return None

            followers_text = await followers_elem.inner_text()
            followers = TikTokDataParser.parse_count(followers_text)

            # 소개 추출 (선택사항)
            bio_elem = await block.query_selector('[data-e2e="search-user-desc"]')
            if not bio_elem:
                bio_elem = await block.query_selector('span[class*="SpanText"]')
            bio = await bio_elem.inner_text() if bio_elem else ''

            # 프로필 URL 추출
            profile_link = await block.query_selector('a[data-e2e="search-user-container"]')
            if not profile_link:
                profile_link = await block.query_selector('a[href*="/@"]')

            profile_url = ''
            if profile_link:
                href = await profile_link.get_attribute('href')
                if href:
                    # 상대 경로인 경우 전체 URL로 변환
                    if href.startswith('/'):
                        profile_url = f"https://www.tiktok.com{href}"
                    elif not href.startswith('http'):
                        profile_url = f"https://www.tiktok.com/{href}"
                    else:
                        profile_url = href

            # profile_link가 없는 경우 username으로 URL 생성
            if not profile_url and username:
                profile_url = f"https://www.tiktok.com/@{username}"

            # 프로필 이미지 URL 추출 - search-user-avatar 내부의 img 태그 찾기
            avatar_container = await block.query_selector('[data-e2e="search-user-avatar"]')
            if avatar_container:
                profile_img_elem = await avatar_container.query_selector('img')
            else:
                # 대체 방법: 직접 img 태그 찾기
                profile_img_elem = await block.query_selector('img[data-e2e="search-user-avatar"]')
                if not profile_img_elem:
                    profile_img_elem = await block.query_selector('img[class*="Avatar"]')

            profile_image_url = await profile_img_elem.get_attribute('src') if profile_img_elem else ''
            
            return {
                'username': username,
                'nickname': nickname,
                'followers': followers,
                'profile_url': profile_url,
                'bio': bio,
                'keyword': keyword,
                'profile_image_url': profile_image_url
            }
            
        except Exception as e:
            print(f"❗사용자 데이터 추출 오류: {e}")
            import traceback
            traceback.print_exc()
            return None

    def _extract_user_data(self, block, keyword: str) -> Optional[Dict]:
        """사용자 블록에서 데이터 추출

        Args:
            block: 사용자 정보 HTML 블록
            keyword: 검색 키워드

        Returns:
            추출된 사용자 데이터 또는 None
        """
        try:
            username_elem = block.query_selector('p[data-e2e="search-user-unique-id"]')
            username = username_elem.inner_text()

            nickname_elem = block.query_selector('p[data-e2e="search-user-nickname"]')
            nickname = nickname_elem.inner_text()

            followers_elem = block.query_selector('span[data-e2e="search-follow-count"]')
            followers_text = followers_elem.inner_text()

            bio_elem = block.query_selector('p[data-e2e="search-user-desc"]')
            bio = bio_elem.inner_text() if bio_elem else ""
            bio = bio.strip() if bio else ""
            
            # 프로필 이미지 URL 추출 및 다운로드
            profile_image = None
            avatar_elem = block.query_selector('[data-e2e="search-user-avatar"] img')
            if avatar_elem:
                original_profile_image = avatar_elem.get_attribute('src')
                # 프로필 이미지 다운로드
                local_profile_path = self._download_image(original_profile_image, username, "profile") if original_profile_image else None
                profile_image = local_profile_path or original_profile_image  # 로컬 경로 우선 사용

            followers = TikTokDataParser.parse_count(followers_text)

            return {
                "keyword": keyword,
                "username": username,
                "nickname": nickname,
                "followers": followers,
                "profile_url": f"https://www.tiktok.com/@{username}",
                "profile_image": profile_image,
                "bio": bio
            }
        except Exception as e:
            print(f"❗데이터 추출 오류: {e}")
            return None

    def _save_single_user(self, user_data: Dict) -> Dict:
        """단일 사용자 데이터를 데이터베이스에 저장
        
        Args:
            user_data: 저장할 사용자 데이터
            
        Returns:
            저장 결과
        """
        
        try:
            # 프로필 이미지 처리 (기존 다운로드된 파일 확인)
            username = user_data.get('username', '')
            original_profile_image = user_data.get('profile_image')
            local_profile_path = None
            
            if original_profile_image and username:
                # 이미 다운로드된 프로필 이미지가 있는지 확인
                user_dir = self.image_base_dir / username
                if user_dir.exists():
                    # profile_로 시작하는 파일이 있는지 확인
                    existing_profiles = list(user_dir.glob("profile_*"))
                    if existing_profiles:
                        # 가장 최근 파일 사용
                        local_profile_path = str(existing_profiles[-1])
            
            repo = TikTokUserRepository(self.db_session)
            stats = repo.upsert_from_scrape([user_data])  # 단일 항목 리스트로 전달
            
            # 사용자가 새로 생성되거나 업데이트된 경우, 프로필 이미지를 관리페이지에 업로드
            if username:
                # 저장된 사용자 레코드 찾기
                user_record = self.db_session.query(TikTokUser).filter(
                    TikTokUser.username == username
                ).first()
                
                if user_record:
                    if local_profile_path:
                        uploaded_url = self._upload_downloaded_image(
                            local_profile_path, username, user_record.id, "user"
                        )
                        if uploaded_url:
                            # 테이블 업데이트: profile_image 컬럼에 업로드된 URL 저장
                            user_record.profile_image = uploaded_url
                            self.db_session.commit()
                            print(f"🖼️ 프로필 이미지 관리페이지 업로드 완료 및 테이블 업데이트: user ID {user_record.id}, URL: {uploaded_url}")
                        else:
                            print(f"❗ 관리페이지 업로드 실패: {username}")
                    else:
                        print(f"❗ 프로필 이미지 파일이 없어 관리페이지 업로드 불가: {username}")
            
            return stats
            
        except Exception as e:
            print(f"❗DB 저장 오류: {e}", flush=True)
            return {'error': str(e)}

    def _update_user_log(self, log_id: int, update_data: Dict) -> None:
        """TikTok 사용자 수집 로그 업데이트
        
        Args:
            log_id: 로그 ID
            update_data: 업데이트할 데이터
        """
        if self.db_handler:
            self.db_handler.update_user_log(log_id, update_data)

    # === VIDEO SCRAPING (USER VIDEOS) ===
    def scrape_user_videos(self, usernames: List[str], use_session: bool = False, session_file: str = "tiktok_sessions/tiktok_session_2_1757409463.json") -> Dict:
        """
        여러 TikTok 사용자의 비디오 정보를 스크래핑합니다.
        
        Args:
            usernames: TikTok 사용자명 리스트
            use_session: 세션 파일 사용 여부
            session_file: 세션 파일 경로
            
        Returns:
            사용자별 비디오 정보 딕셔너리
        """
        
        async def _scrape_videos():
            """내부 비동기 스크래핑 함수"""
            all_results = {}
            db_results = {}

            try:
                async with AsyncBrowserManager() as browser_manager:
                    # 브라우저 초기화
                    session_file_to_use = session_file if use_session else None
                    await browser_manager.initialize(headless=False, session_file=session_file_to_use)
                    
                    # TikTok 메인 페이지로 이동하여 세션 활성화
                    await browser_manager.navigate_to_main_page()
                    
                    page = browser_manager.page
                    
                    # 로그인 상태 확인 (세션 사용 시)
                    if use_session:
                        try:
                            await page.wait_for_selector('[data-e2e="nav-profile"]', timeout=10000)
                            print("✅ 로그인 상태 확인됨")
                        except:
                            print("⚠️ 로그인 세션을 확인할 수 없습니다. 계속 진행합니다.")
                    
                    # 각 사용자별로 스크래핑 실행
                    for idx, username in enumerate(usernames, 1):
                        print("\n" + "=" * 60)
                        print(f"[{idx}/{len(usernames)}] '{username}' 사용자 처리 중...")
                        print("=" * 60)
                        
                        # page를 전달하여 스크래핑 함수 호출
                        results = await self._scrape_single_user_videos_async(browser_manager, username)
                        all_results[username] = results
                        
                        # 각 사용자별로 데이터베이스에 저장
                        if results:
                            db_result = self._save_video_results_to_db(results, username)
                            db_results[username] = db_result
                        
                        # 마지막 사용자가 아니면 잠시 대기
                        if idx < len(usernames):
                            wait_time = random.uniform(5, 10)
                            print(f"⏳ 다음 사용자 처리 전 {wait_time:.1f}초 대기...")
                            await page.wait_for_timeout(wait_time * 1000)
                    
                    print("\n" + "=" * 60)
                    print("✅ 모든 사용자 스크래핑 완료!")
                    print("=" * 60)
                    
                    return {
                        "success": True,
                        "total_users": len(usernames),
                        "results": all_results,
                        "db_save_results": db_results,
                        "message": f"Successfully scraped {len(usernames)} users and saved to database"
                    }
                    
            except Exception as e:
                print(f"❌ 스크래핑 중 전체 오류 발생: {e}")
                import traceback
                traceback.print_exc()
                return {
                    "success": False,
                    "error": str(e),
                    "results": all_results,
                    "db_save_results": db_results
                }
        
        # 비동기 함수 실행
        return asyncio.run(_scrape_videos())

    async def _scrape_single_user_videos_async(self, browser_manager, username: str) -> List[Dict]:
        """
        단일 사용자의 비디오 정보를 추출합니다 (async 버전)

        Args:
            page: async playwright page 객체
            username: TikTok 사용자명

        Returns:
            비디오 정보 리스트
        """
        results = []

        try:
            # CAPTCHA 방지를 위한 안전한 프로필 페이지 이동
            if not await browser_manager.navigate_to_profile(username):
                return results

            # 페이지 콘텐츠가 완전히 로드될 때까지 대기
            print("⏳ 비디오 콘텐츠 로딩 대기 중...")
            await browser_manager.wait_for_video_containers()

            # 비디오 컨테이너 찾기 (ID 기반으로 검색)
            print("🎬 비디오 항목들을 검색 중...")
            video_containers = await browser_manager.get_video_containers()

            print(f"📸 총 {len(video_containers)}개의 비디오를 발견했습니다.")

            for i, container in enumerate(video_containers, 1):
                try:
                    result_item = {
                        'index': i,
                        'username': username
                    }

                    # 링크 추출
                    link = await container.get_attribute('href')
                    if link:
                        # 상대 경로인 경우 절대 경로로 변환
                        if link.startswith('/'):
                            link = f"https://www.tiktok.com{link}"
                        result_item['link'] = link
                    else:
                        result_item['link'] = 'N/A'

                    # picture 태그 내부의 img 태그 찾기 (alt 값 추출)
                    img_element = await container.query_selector('picture img')
                    if img_element:
                        alt_text = await img_element.get_attribute('alt')
                        src = await img_element.get_attribute('src')

                        # '(으)로 만든' 뒤의 텍스트만 추출
                        if alt_text:
                            if '으로 만든' in alt_text:
                                alt_text = alt_text.split('으로 만든', 1)[1].strip()
                            elif '로 만든' in alt_text:
                                alt_text = alt_text.split('로 만든', 1)[1].strip()

                        result_item['alt'] = alt_text if alt_text else 'N/A'
                        result_item['src'] = src if src else 'N/A'
                    else:
                        result_item['alt'] = 'N/A'
                        result_item['src'] = 'N/A'

                    # 조회수 추출 (strong 태그 with data-e2e="video-views")
                    views_element = await container.query_selector('strong[data-e2e="video-views"]')
                    if views_element:
                        views_text = await views_element.inner_text()
                        result_item['views'] = views_text.strip() if views_text else 'N/A'
                    else:
                        result_item['views'] = 'N/A'

                    results.append(result_item)
                    print(f"✔ Video {i}: {result_item.get('alt', 'N/A')[:50]}... | Views: {result_item.get('views', 'N/A')}")

                except Exception as e:
                    print(f"❗ Video {i} 처리 중 오류: {e}")
                    continue

            print(f"🎯 총 {len(results)}개의 비디오 정보를 추출했습니다.")

            # 통계 표시
            alt_with_text = len([r for r in results if r.get('alt', 'N/A') != 'N/A'])
            views_with_data = len([r for r in results if r.get('views', 'N/A') != 'N/A'])
            links_with_data = len([r for r in results if r.get('link', 'N/A') != 'N/A'])

            print(f"📊 상세 통계:")
            print(f"   - alt 값이 있는 비디오: {alt_with_text}개")
            print(f"   - 조회수가 있는 비디오: {views_with_data}개")
            print(f"   - 링크가 있는 비디오: {links_with_data}개")
            print(f"   - 전체 비디오: {len(results)}개")

        except Exception as e:
            await browser_manager.take_screenshot(f'debug_{username}_error.png')
            raise TikTokScrapingException(
                f"비디오 수집 중 오류: {str(e)}",
                context={"username": username, "operation": "scrape_videos"}
            )

        return results

    def scrape_user_repost_videos(self, usernames: List[str], use_session: bool = False, session_file: str = "tiktok_auth.json") -> Dict:
        """
        여러 TikTok 사용자의 리포스트 비디오 정보를 스크래핑합니다.
        
        Args:
            usernames: TikTok 사용자명 리스트
            use_session: 세션 파일 사용 여부
            session_file: 세션 파일 경로
            
        Returns:
            사용자별 리포스트 비디오 정보 딕셔너리
        """
        
        async def _scrape_repost_videos():
            """내부 비동기 리포스트 스크래핑 함수"""
            all_results = {}
            db_results = {}
            
            try:
                async with AsyncBrowserManager() as browser_manager:
                    # 브라우저 초기화
                    session_file_to_use = session_file if use_session else None
                    await browser_manager.initialize(headless=False, session_file=session_file_to_use)
                    
                    # TikTok 메인 페이지로 이동하여 세션 활성화
                    await browser_manager.navigate_to_main_page()
                    
                    page = browser_manager.page
                    
                    # 로그인 상태 확인 (세션 사용 시)
                    if use_session:
                        try:
                            await page.wait_for_selector('[data-e2e="nav-profile"]', timeout=10000)
                            print("✅ 로그인 상태 확인됨")
                        except:
                            print("⚠️ 로그인 세션을 확인할 수 없습니다. 계속 진행합니다.")
                    
                    # 각 사용자별로 리포스트 스크래핑 실행
                    for idx, username in enumerate(usernames, 1):
                        print("\n" + "=" * 60)
                        print(f"[{idx}/{len(usernames)}] '{username}' 사용자 리포스트 처리 중...")
                        print("=" * 60)
                        
                        # page를 전달하여 스크래핑 함수 호출
                        results = await self._scrape_single_user_repost_videos_async(page, username)
                        all_results[username] = results
                        
                        # 각 사용자별로 데이터베이스에 저장 (리포스트는 별도 필드로 저장)
                        if results:
                            db_result = self._save_video_results_to_db(results, username, is_repost=True)
                            db_results[username] = db_result
                        
                        # 마지막 사용자가 아니면 잠시 대기
                        if idx < len(usernames):
                            wait_time = random.uniform(5, 10)
                            print(f"\n⏳ 다음 사용자 처리까지 {wait_time:.1f}초 대기...")
                            await page.wait_for_timeout(wait_time * 1000)
                    
                    print("\n" + "=" * 60)
                    print("✅ 모든 사용자 리포스트 스크래핑 완료!")
                    print("=" * 60)
                    
                    return {
                        "success": True,
                        "total_users": len(usernames),
                        "results": all_results,
                        "db_save_results": db_results,
                        "message": f"Successfully scraped repost videos for {len(usernames)} users"
                    }
                    
            except Exception as e:
                print(f"❌ 리포스트 스크래핑 중 전체 오류 발생: {e}")
                import traceback
                traceback.print_exc()
                return {
                    "success": False,
                    "error": str(e),
                    "results": all_results,
                    "db_save_results": db_results
                }
        
        # 비동기 함수 실행
        return asyncio.run(_scrape_repost_videos())

    async def _scrape_single_user_repost_videos_async(self, page, username: str) -> List[Dict]:
        """
        단일 사용자의 리포스트 비디오 정보를 추출합니다 (async 버전)
        
        Args:
            page: async playwright page 객체
            username: TikTok 사용자명
            
        Returns:
            리포스트 비디오 정보 리스트
        """
        results = []
        try:
            async with AsyncBrowserManager() as browser_manager:
                # CAPTCHA 방지를 위한 안전한 프로필 페이지 이동
                browser_manager.page = page
                if not await browser_manager.navigate_to_profile(username):
                    return results

                # Repost 탭 클릭
                print("🔄 Repost 탭을 찾는 중...")
                try:
                    # data-e2e="repost-tab" 속성을 가진 요소 찾기
                    repost_tab = page.locator('[data-e2e="repost-tab"]')

                    if await repost_tab.count() > 0:
                        print("✅ Repost 탭을 찾았습니다. 클릭합니다...")
                        try:
                            # timeout을 5초로 제한하여 클릭 시도
                            await repost_tab.click(timeout=5000)
                            await page.wait_for_timeout(random.uniform(3000, 5000))  # 클릭 후 로딩 대기
                            print("✅ Repost 탭이 활성화되었습니다.")
                        except Exception as click_error:
                            # Timeout 에러 발생 시 이 사용자 건너뛰기
                            if "Timeout" in str(click_error):
                                print(f"⚠️ Repost 탭 클릭 시 timeout 발생. 사용자 {username} 건너뜁니다.")
                                return results
                            else:
                                print(f"⚠️ Repost 탭 클릭 중 오류: {click_error}")
                                return results
                    else:
                        print("⚠️ Repost 탭을 찾을 수 없습니다. 사용자에게 리포스트가 없을 수 있습니다.")
                        return results

                except Exception as e:
                    print(f"⚠️ Repost 탭 처리 중 오류: {e}")
                    # 리포스트 탭 관련 오류 발생 시 이 사용자 건너뛰기
                    return results

                # 페이지 콘텐츠가 완전히 로드될 때까지 대기
                print("⏳ 리포스트 비디오 콘텐츠 로딩 대기 중...")
                try:
                    # ID가 column-item-video-container-로 시작하는 요소가 나타날 때까지 대기 (최대 10초)
                    await page.wait_for_selector('[id^="column-item-video-container-"]', timeout=10000)
                    await page.wait_for_timeout(random.uniform(3000, 5000))  # 추가 대기
                except:
                    print("⚠️ 리포스트 비디오 컨테이너를 찾는 중 타임아웃. 계속 진행합니다...")

                # 리포스트 비디오 컨테이너 찾기 (ID 기반으로 검색)
                print("🎬 리포스트 비디오 항목들을 검색 중...")
                containers = await page.query_selector_all('[id^="column-item-video-container-"]')
                video_containers = []

                for container in containers:
                    # 각 컨테이너에서 첫 번째 a 태그만 가져오기
                    first_link = await container.query_selector('a')
                    if first_link:
                        video_containers.append(first_link)

                # 대체 방법: 직접 ID로 찾기
                if len(video_containers) == 0:
                    print("ID 선택자로 못 찾음. 다른 방법 시도...")
                    video_containers = []
                    # 0부터 시작하는 숫자로 ID를 직접 찾기
                    for i in range(100):  # 최대 100개까지 확인
                        container = await page.query_selector(f'#column-item-video-container-{i}')
                        if container:
                            # 컨테이너에서 첫 번째 a 태그만 가져오기
                            first_link = await container.query_selector('a')
                            if first_link:
                                video_containers.append(first_link)
                        else:
                            # 연속된 번호가 없으면 중단 (처음 몇 개는 스킵 가능)
                            if i > 10 and len(video_containers) > 0:
                                break

                print(f"📸 총 {len(video_containers)}개의 리포스트 비디오를 발견했습니다.")

                for i, container in enumerate(video_containers, 1):
                    try:
                        result_item = {
                            'index': i,
                            'username': username,
                            'is_repost': True  # 리포스트임을 표시
                        }

                        # 링크 추출
                        link = await container.get_attribute('href')
                        if link:
                            # 상대 경로인 경우 절대 경로로 변환
                            if link.startswith('/'):
                                link = f"https://www.tiktok.com{link}"
                            result_item['link'] = link
                        else:
                            result_item['link'] = 'N/A'

                        # picture 태그 내부의 img 태그 찾기 (alt 값 추출)
                        img_element = await container.query_selector('picture img')
                        if img_element:
                            alt_text = await img_element.get_attribute('alt')
                            src = await img_element.get_attribute('src')

                            # '(으)로 만든' 뒤의 텍스트만 추출
                            if alt_text:
                                if '으로 만든' in alt_text:
                                    alt_text = alt_text.split('으로 만든', 1)[1].strip()
                                elif '로 만든' in alt_text:
                                    alt_text = alt_text.split('로 만든', 1)[1].strip()

                            result_item['alt'] = alt_text if alt_text else 'N/A'
                            result_item['src'] = src if src else 'N/A'
                        else:
                            result_item['alt'] = 'N/A'
                            result_item['src'] = 'N/A'

                        # 조회수 추출 (strong 태그 with data-e2e="video-views")
                        views_element = await container.query_selector('strong[data-e2e="video-views"]')
                        if views_element:
                            views_text = await views_element.inner_text()
                            result_item['views'] = views_text.strip() if views_text else 'N/A'
                        else:
                            result_item['views'] = 'N/A'

                        results.append(result_item)
                        print(f"✔ Repost {i}: {result_item.get('alt', 'N/A')[:50]}... | Views: {result_item.get('views', 'N/A')}")

                    except Exception as e:
                        print(f"❗ Repost {i} 처리 중 오류: {e}")
                        continue

                print(f"🎯 총 {len(results)}개의 리포스트 비디오 정보를 추출했습니다.")

                # 통계 표시
                alt_with_text = len([r for r in results if r.get('alt', 'N/A') != 'N/A'])
                views_with_data = len([r for r in results if r.get('views', 'N/A') != 'N/A'])
                links_with_data = len([r for r in results if r.get('link', 'N/A') != 'N/A'])

                print(f"📊 상세 통계:")
                print(f"   - alt 값이 있는 리포스트: {alt_with_text}개")
                print(f"   - 조회수가 있는 리포스트: {views_with_data}개")
                print(f"   - 링크가 있는 리포스트: {links_with_data}개")
                print(f"   - 전체 리포스트: {len(results)}개")
            
        except Exception as e:
            await page.screenshot(path=f'debug_{username}_repost_error.png')
            raise TikTokScrapingException(
                f"리포스트 비디오 수집 중 오류: {str(e)}",
                context={"username": username, "operation": "scrape_repost_videos"}
            )
        
        return results

    # === BRAND ACCOUNT & REPOST VIDEOS ===
    def scrape_brand_repost_videos(
        self, 
        brand_username: str, 
        max_videos: int = 20,
        use_session: bool = False,
        session_file: str = "tiktok_auth.json"
    ) -> Dict:
        """
        브랜드 계정의 리포스트 비디오를 수집합니다.
        
        Args:
            brand_username: 브랜드 TikTok 계정명
            max_videos: 수집할 최대 비디오 수
            use_session: 세션 파일 사용 여부
            session_file: 세션 파일 경로
            
        Returns:
            수집 결과 딕셔너리
        """
        
        async def _scrape_brand_reposts():
            """내부 비동기 브랜드 리포스트 스크래핑 함수"""
            result = {
                "brand_account": None,
                "repost_videos": [],
                "stats": {
                    "total_videos": 0,
                    "new_videos": 0,
                    "updated_videos": 0,
                    "errors": 0
                }
            }
            
            try:
                async with AsyncBrowserManager() as browser_manager:
                    # 브라우저 초기화
                    session_file_to_use = session_file if use_session else None
                    await browser_manager.initialize(headless=False, session_file=session_file_to_use)
                    
                    # TikTok 메인 페이지로 이동하여 세션 활성화
                    await browser_manager.navigate_to_main_page()
                    
                    page = browser_manager.page
                    
                    # 브랜드 계정 정보 확인/생성
                    brand_account = self._get_or_create_brand_account(brand_username)
                    
                    result["brand_account"] = brand_account.to_dict()
                    
                    # 브랜드 계정 페이지 방문
                    url = f"https://www.tiktok.com/@{brand_username}"
                    print(f"Visiting brand account: {url}")
                    await page.goto(url, wait_until="networkidle")
                    await page.wait_for_timeout(random.uniform(3000, 5000))
                    
                    # 프로필 정보 업데이트
                    try:
                        # 닉네임
                        nickname_elem = await page.query_selector('[data-e2e="user-title"]')
                        if nickname_elem:
                            brand_account.nickname = await nickname_elem.inner_text()
                        
                        # 팔로워 수
                        followers_elem = await page.query_selector('[data-e2e="followers-count"]')
                        if followers_elem:
                            brand_account.followers = TikTokDataParser.parse_count(await followers_elem.inner_text())
                        
                        # 팔로잉 수
                        following_elem = await page.query_selector('[data-e2e="following-count"]')
                        if following_elem:
                            brand_account.following_count = TikTokDataParser.parse_count(await following_elem.inner_text())
                        
                        # 비디오 수
                        video_count_elem = await page.query_selector('[data-e2e="video-count"]')
                        if video_count_elem:
                            brand_account.video_count = TikTokDataParser.parse_count(await video_count_elem.inner_text())
                        
                        # 프로필 이미지
                        profile_img_elem = await page.query_selector('[data-e2e="user-avatar"] img')
                        if profile_img_elem:
                            brand_account.profile_image = await profile_img_elem.get_attribute("src")
                        
                        # Bio
                        bio_elem = await page.query_selector('[data-e2e="user-bio"]')
                        if bio_elem:
                            brand_account.bio = await bio_elem.inner_text()
                        
                        # 인증 마크
                        verified_elem = await page.query_selector('[data-e2e="verified-badge"]')
                        brand_account.is_verified = verified_elem is not None
                        
                        brand_account.profile_url = url
                        brand_account.last_scraped_at = datetime.now()
                        brand_account.updated_at = datetime.now()
                        
                        self.db_session.commit()
                        print(f"Updated brand account profile: {brand_username}")
                        
                    except Exception as e:
                        print(f"Error updating brand profile: {e}")
                    
                    # 리포스트 탭으로 이동 (있는 경우)
                    try:
                        # 리포스트 탭 찾기
                        repost_tab = await page.query_selector('a[href*="/reposts"], [data-e2e="reposts-tab"], [data-e2e="repost-tab"]')
                        if repost_tab:
                            print("Found reposts tab, clicking...")
                            try:
                                # timeout을 5초로 제한하여 클릭 시도
                                await repost_tab.click(timeout=5000)
                                await page.wait_for_timeout(random.uniform(3000, 5000))
                            except Exception as click_error:
                                # Timeout 에러 발생 시 이 계정 건너뛰기
                                if "Timeout" in str(click_error):
                                    print(f"⚠️ Repost 탭 클릭 시 timeout 발생. 브랜드 계정 {brand_username} 건너뜁니다.")
                                    return result
                                else:
                                    print(f"⚠️ Repost 탭 클릭 중 오류: {click_error}")
                                    # 메인 피드에서 수집 시도
                                    print("메인 피드에서 수집을 시도합니다...")
                        else:
                            print("No reposts tab found, collecting from main feed")
                    except Exception as e:
                        print(f"Could not navigate to reposts tab: {e}")
                        # 메인 피드에서 수집 계속 진행
                    
                    # 비디오 수집 - 스크롤 없이 처음 보이는 것들만
                    collected_videos = []
                    
                    # 현재 보이는 비디오 요소들 찾기 (스크롤 X)
                    video_elements = await page.query_selector_all('[data-e2e="user-post-item"]')
                    print(f"Found {len(video_elements)} video elements on page")
                    
                    for video_elem in video_elements:
                        if len(collected_videos) >= max_videos:
                            break
                        
                        try:
                            # 비디오 링크
                            link_elem = await video_elem.query_selector('a')
                            if not link_elem:
                                continue
                            
                            video_url = await link_elem.get_attribute('href')
                            if not video_url:
                                continue
                            
                            # 중복 체크
                            if any(v['video_url'] == video_url for v in collected_videos):
                                continue
                            
                            video_data = {
                                'video_url': video_url,
                                'repost_username': brand_username
                            }
                            
                            # 썸네일
                            thumbnail_elem = await video_elem.query_selector('img')
                            if thumbnail_elem:
                                video_data['thumbnail_url'] = await thumbnail_elem.get_attribute('src')
                                video_data['title'] = await thumbnail_elem.get_attribute('alt') or ''
                            
                            # 조회수
                            views_elem = await video_elem.query_selector('[data-e2e="video-views"]')
                            if views_elem:
                                video_data['view_count'] = TikTokDataParser.parse_count(await views_elem.inner_text())
                            
                            # 리포스트 정보 확인 (리포스트인 경우)
                            repost_info_elem = await video_elem.query_selector('[data-e2e="repost-info"], .repost-info')
                            if repost_info_elem:
                                # 원본 사용자명 추출
                                original_user_elem = await repost_info_elem.query_selector('a')
                                if original_user_elem:
                                    original_username = (await original_user_elem.inner_text()).replace('@', '')
                                    video_data['original_username'] = original_username
                            
                            collected_videos.append(video_data)
                            print(f"Collected video {len(collected_videos)}: {video_url}")
                            
                        except Exception as e:
                            print(f"Error collecting video: {e}")
                            result["stats"]["errors"] += 1
                    
                    result["stats"]["total_videos"] = len(collected_videos)
                    
                    # DB에 저장
                    for video_data in collected_videos:
                        try:
                            # 기존 비디오 확인
                            existing_video = self.db_session.query(TikTokRepostVideo).filter(
                                TikTokRepostVideo.tiktok_brand_account_id == brand_account.id,
                                TikTokRepostVideo.video_url == video_data['video_url']
                            ).first()
                            
                            if existing_video:
                                # 업데이트
                                if 'view_count' in video_data:
                                    existing_video.view_count = video_data['view_count']
                                if 'title' in video_data:
                                    existing_video.title = video_data['title']
                                if 'thumbnail_url' in video_data:
                                    existing_video.thumbnail_url = video_data['thumbnail_url']
                                existing_video.updated_at = datetime.now()
                                result["stats"]["updated_videos"] += 1
                            else:
                                # 새로 생성
                                new_video = TikTokRepostVideo.from_scrape_data(video_data, brand_account.id)
                                self.db_session.add(new_video)
                                result["stats"]["new_videos"] += 1
                            
                            self.db_session.commit()
                            result["repost_videos"].append(video_data)
                            
                        except Exception as e:
                            print(f"Error saving video to DB: {e}")
                            self.db_session.rollback()
                            result["stats"]["errors"] += 1
                    
                    print(f"\nCollection complete for {brand_username}:")
                    print(f"  Total videos: {result['stats']['total_videos']}")
                    print(f"  New videos: {result['stats']['new_videos']}")
                    print(f"  Updated videos: {result['stats']['updated_videos']}")
                    print(f"  Errors: {result['stats']['errors']}")
                    
                    return result
                    
            except Exception as e:
                print(f"Error in scrape_brand_repost_videos: {e}")
                result["error"] = str(e)
                return result
        
        # 비동기 함수 실행
        return asyncio.run(_scrape_brand_reposts())

    def _get_or_create_brand_account(self, username: str) -> 'TikTokBrandAccount':
        """
        브랜드 계정을 조회하거나 새로 생성합니다.
        
        Args:
            username: 브랜드 계정명
            
        Returns:
            TikTokBrandAccount 인스턴스
        """
        if not self.db_handler:
            raise ValueError("Database handler is required")
        
        return self.db_handler.get_or_create_brand_account(username)

    # === DATABASE OPERATIONS ===
    def _save_video_results_to_db(self, results: List[Dict], username: str, is_repost: bool = False) -> Dict:
        """
        추출된 비디오 결과를 데이터베이스에 저장합니다.
        
        Args:
            results: 추출된 비디오 데이터
            username: 사용자명
            is_repost: 리포스트 비디오 여부
            
        Returns:
            저장 결과 통계
        """
        if not self.db_session:
            print("⚠️ 데이터베이스 세션이 없습니다. 저장을 건너뜁니다.")
            return {"error": "No database session"}
        
        try:
            if is_repost:
                # 리포스트 비디오를 위한 브랜드 계정 조회/생성
                brand_account = self._get_or_create_brand_account(username)
                brand_account_id = brand_account.id
                saved_count = 0
                
                # 리포스트 비디오 데이터를 tiktok_repost_videos 테이블에 저장
                for video_data in results:
                    try:
                        # 썸네일 이미지 다운로드
                        original_thumbnail = video_data.get('src', '')
                        local_thumbnail_path = TikTokImageUtils.download_image(original_thumbnail, username, "repost_thumb", self.image_base_dir) if original_thumbnail else None
                        
                        # 데이터 매핑
                        repost_data = {
                            'video_url': video_data.get('link', ''),
                            'title': video_data.get('alt', ''),
                            'thumbnail_url': original_thumbnail,  # 원본 URL 저장 (관리페이지 업로드 후 업데이트)
                            'view_count': TikTokDataParser.parse_count(video_data.get('views', '0')),
                            'repost_username': username
                        }
                        
                        # 중복 체크
                        existing_video = self.db_session.query(TikTokRepostVideo).filter(
                            TikTokRepostVideo.tiktok_brand_account_id == brand_account_id,
                            TikTokRepostVideo.video_url == repost_data['video_url']
                        ).first()
                        
                        repost_record = None
                        if existing_video:
                            # 동시성 문제 해결을 위한 재시도 로직
                            max_retries = 3
                            for attempt in range(max_retries):
                                try:
                                    # 세션 새로고침으로 최신 데이터 가져오기
                                    self.db_session.refresh(existing_video)
                                    
                                    # 기존 리포스트 비디오 정보 업데이트
                                    existing_video.title = repost_data['title']
                                    existing_video.view_count = repost_data['view_count']
                                    existing_video.updated_at = datetime.now()
                                    
                                    # 즉시 커밋하여 락 해제
                                    self.db_session.commit()
                                    repost_record = existing_video
                                    print(f"🔄 기존 리포스트 비디오 업데이트: {repost_data['video_url'][:50]}...")
                                    break
                                    
                                except Exception as retry_error:
                                    self.db_session.rollback()
                                    if attempt < max_retries - 1:
                                        print(f"⚠️ 업데이트 재시도 {attempt + 1}/{max_retries}: {retry_error}")
                                        time.sleep(0.1 * (attempt + 1))  # 지수 백오프
                                        # 레코드 다시 조회
                                        existing_video = self.db_session.query(TikTokRepostVideo).filter(
                                            TikTokRepostVideo.tiktok_brand_account_id == brand_account_id,
                                            TikTokRepostVideo.video_url == repost_data['video_url']
                                        ).first()
                                        if not existing_video:
                                            print(f"⚠️ 레코드가 삭제됨, 새로 생성합니다.")
                                            break
                                    else:
                                        print(f"❌ 최대 재시도 횟수 초과: {retry_error}")
                                        raise retry_error
                        
                        if not repost_record:
                            # 새 리포스트 비디오 생성 및 저장
                            repost_video = TikTokRepostVideo.from_scrape_data(repost_data, brand_account_id)
                            self.db_session.add(repost_video)
                            self.db_session.commit()  # 즉시 커밋
                            repost_record = repost_video
                            print(f"✅ 새 리포스트 비디오 추가: {repost_data['video_url'][:50]}...")
                        
                        # 이미지를 관리페이지에 업로드하고 URL 업데이트
                        if local_thumbnail_path and repost_record:
                            try:
                                uploaded_url = TikTokImageUtils.upload_downloaded_image(
                                    local_thumbnail_path, username, repost_record.id, "repost_video", settings.ADMIN_URL
                                )
                                if uploaded_url:
                                    # 별도의 트랜잭션으로 썸네일 URL 업데이트
                                    max_retries = 3
                                    for retry in range(max_retries):
                                        try:
                                            # 새로운 세션에서 레코드 다시 조회
                                            updated_record = self.db_session.query(TikTokRepostVideo).filter(
                                                TikTokRepostVideo.id == repost_record.id
                                            ).first()
                                            if updated_record:
                                                updated_record.thumbnail_url = uploaded_url
                                                self.db_session.commit()
                                                print(f"🖼️ 리포스트 썸네일 관리페이지 업로드 완료: repost video ID {repost_record.id}")
                                                break
                                        except Exception as update_error:
                                            self.db_session.rollback()
                                            if retry < max_retries - 1:
                                                time.sleep(0.1 * (retry + 1))
                                            else:
                                                print(f"⚠️ 썸네일 URL 업데이트 실패: {update_error}")
                            except Exception as upload_error:
                                print(f"⚠️ 썸네일 업로드 중 오류 (무시됨): {upload_error}")

                        saved_count += 1

                    except Exception as e:
                        print(f"⚠️ 리포스트 비디오 저장 중 오류: {e}")
                        # 트랜잭션 롤백
                        self.db_session.rollback()
                        continue
                        
            else:
                # 일반 비디오 저장 (기존 로직)
                user_repo = TikTokUserRepository(self.db_session)
                tiktok_user = user_repo.get_by_username(username)
                
                if not tiktok_user:
                    raise TikTokUserNotFoundException(username, table="tiktok_users")
                
                tiktok_user_id = tiktok_user.id
                saved_count = 0
                
                # 각 비디오 데이터를 데이터베이스에 저장
                for video_data in results:
                    try:
                        # 썸네일 이미지 다운로드
                        original_thumbnail = video_data.get('src', '')
                        local_thumbnail_path = TikTokImageUtils.download_image(original_thumbnail, username, "video_thumb", self.image_base_dir) if original_thumbnail else None
                        
                        # 데이터 매핑: link->video_url, alt->title, src->thumbnail_url, views->view_count
                        mapped_data = {
                            'link': video_data.get('link', ''),
                            'alt': video_data.get('alt', ''),
                            'src': original_thumbnail,  # 원본 URL 저장 (관리페이지 업로드 후 업데이트)
                            'views': TikTokDataParser.parse_count(video_data.get('views', '0'))
                        }
                        
                        # 중복 체크 (같은 video_url이 이미 있는지 확인)
                        existing_video = self.db_session.query(TikTokVideo).filter(
                            TikTokVideo.tiktok_user_id == tiktok_user_id,
                            TikTokVideo.video_url == mapped_data['link']
                        ).first()
                        
                        video_record = None
                        if existing_video:
                            # 기존 비디오 정보 업데이트
                            existing_video.title = mapped_data['alt']
                            existing_video.view_count = mapped_data['views']
                            video_record = existing_video
                            print(f"🔄 기존 비디오 업데이트: {mapped_data['link'][:50]}...")
                        else:
                            # 새 비디오 생성 및 저장
                            video = TikTokVideo.from_scrape_data(mapped_data, tiktok_user_id)
                            self.db_session.add(video)
                            self.db_session.flush()  # ID 생성을 위해 flush
                            video_record = video
                            print(f"✅ 새 비디오 추가: {mapped_data['link'][:50]}...")
                        
                        # 이미지를 관리페이지에 업로드하고 URL 업데이트
                        if local_thumbnail_path and video_record:
                            uploaded_url = TikTokImageUtils.upload_downloaded_image(
                                local_thumbnail_path, username, video_record.id, "video", settings.ADMIN_URL
                            )
                            if uploaded_url:
                                video_record.thumbnail_url = uploaded_url
                                print(f"🖼️ 썸네일 관리페이지 업로드 완료: video ID {video_record.id}")
                        
                        saved_count += 1
                        
                    except Exception as e:
                        print(f"⚠️ 비디오 저장 중 오류: {e}")
                        continue
            
            # 커밋
            self.db_session.commit()
            
            print(f"✅ {username}: 총 {len(results)}개 중 {saved_count}개 비디오를 데이터베이스에 저장했습니다.")
            
            if is_repost:
                return {
                    "success": True,
                    "username": username,
                    "total_videos": len(results),
                    "saved_videos": saved_count,
                    "brand_account_id": brand_account_id
                }
            else:
                return {
                    "success": True,
                    "username": username,
                    "total_videos": len(results),
                    "saved_videos": saved_count,
                    "tiktok_user_id": tiktok_user_id
                }
            
        except TikTokUserNotFoundException as e:
            print(f"❌ 사용자 '{e.username}'을 {e.table}에서 찾을 수 없습니다.")
            return {"error": e.message}
        except Exception as e:
            print(f"❌ 데이터베이스 저장 중 오류: {e}")
            self.db_session.rollback()
            return {"error": str(e)}

    # === MESSAGE SYSTEM ===
    def send_bulk_tiktok_messages(
        self,
        usernames: List[str],
        session_file_name: str | None = None,
        template_code: str = None,
        message_id: int | None = None
    ):
        """
        여러 TikTok 사용자에게 메시지 일괄 전송 (브라우저 유지)
        
        Args:
            usernames: 메시지를 받을 TikTok 사용자명 리스트 (@없이)
            session_file_name: 세션 파일 경로
            template_code: 템플릿 코드 (필수)
            message_id: 메시지 ID (중복 방지용)
        
        Returns:
            전체 전송 결과
        """
        # message_id가 제공된 경우 중복 처리 방지 체크
        if message_id:
            duplicate_check = self._check_and_mark_message_processing(message_id)
            if not duplicate_check["success"]:
                return duplicate_check

        import asyncio
        
        async def _send_bulk_messages():
            """내부 비동기 일괄 메시지 전송 함수 (browser_manager 사용)"""
            
            # 세션 파일 경로 설정
            if not session_file_name:
                session_file_name_local = "tiktok_auth_default.json"
            else:
                session_file_name_local = session_file_name
            
            # 세션 파일 존재 확인
            if not os.path.exists(session_file_name_local):
                return {"error": f"세션 파일 '{session_file_name_local}'을 찾을 수 없습니다. 먼저 로그인하세요."}
            
            print(f"📬 {len(usernames)}명의 사용자에게 메시지 전송 시작...")
            
            # 메시지 템플릿 로드
            self.load_message_templates(template_code)
            
            results = []
            success_count = 0
            fail_count = 0
            
            try:
                async with AsyncBrowserManager() as browser_manager:
                    await browser_manager.initialize(headless=False, session_file=session_file_name_local)
                    
                    # 초기 세션 활성화 (메인 페이지로 이동)
                    await browser_manager.navigate_to_main_page()
                    
                    # 각 사용자에게 메시지 전송
                    for i, username in enumerate(usernames, 1):
                        print(f"\n--- {i}/{len(usernames)}: {username} 처리 중 ---")
                        
                        try:
                            # 메시지 생성 (항상 템플릿 사용 - 각 사용자마다 랜덤 조합)
                            message = self._get_random_message_template()
                            
                            # browser_manager를 사용해 메시지 전송
                            result = await browser_manager.send_direct_message(username, message)
                            
                            # DB 업데이트
                            if message_id and self.db_session:
                                try:
                                    user = self.db_session.query(TikTokUser).filter(
                                        TikTokUser.username == username
                                    ).first()

                                    if user:
                                        self.upsert_message_log(
                                            user.id,
                                            message_id,
                                            message,
                                            "success" if result["success"] else "fail",
                                            result["message"]
                                        )

                                        # 메시지 전송 성공 시 status 업데이트 (unconfirmed -> dm_sent)
                                        if result["success"] and user.status == 'unconfirmed':
                                            user.status = 'dm_sent'
                                            self.db_session.commit()
                                            print(f"[SUCCESS] {username} 사용자 상태 변경: unconfirmed -> dm_sent")

                                    # 실시간으로 카운트 업데이트
                                    self._update_message_count(message_id, result["success"])
                                except Exception as db_error:
                                    print(f"[ERROR] DB 업데이트 실패: {db_error}")
                            
                            if result["success"]:
                                success_count += 1
                            else:
                                fail_count += 1
                            
                            results.append({
                                "username": username,
                                "success": result["success"],
                                "message": result["message"],
                                "sent_message": result["sent_message"]
                            })
                            
                            # 다음 사용자를 위한 대기 시간 (계정 보호)
                            if i < len(usernames):
                                wait_minutes = random.uniform(1, 5)
                                wait_seconds = wait_minutes * 60
                                print(f"다음 작업을 위해 {wait_minutes:.2f}분 ({int(wait_seconds)}초) 대기합니다...")
                                await browser_manager.page.wait_for_timeout(wait_seconds * 1000)
                            
                        except Exception as user_error:
                            print(f"[ERROR] {username} 처리 중 오류: {user_error}")
                            
                            # DB 업데이트 (일반 오류)
                            if message_id and self.db_session:
                                try:
                                    user = self.db_session.query(TikTokUser).filter(
                                        TikTokUser.username == username
                                    ).first()
                                    
                                    if user:
                                        self.upsert_message_log(
                                            user.id, 
                                            message_id, 
                                            self._get_random_message_template(),
                                            "fail",
                                            f"오류: {str(user_error)}"
                                        )
                                    
                                    # 실시간으로 fail_count 업데이트
                                    self._update_message_count(message_id, False)
                                except Exception as db_error:
                                    print(f"[ERROR] DB 업데이트 실패: {db_error}")
                            
                            fail_count += 1
                            results.append({
                                "username": username,
                                "success": False,
                                "message": f"오류: {str(user_error)}",
                                "sent_message": None
                            })
                    
                    print("\n모든 사용자에게 메시지 전송이 완료되었습니다.")
            
            except Exception as e:
                raise TikTokBrowserException(f"브라우저 오류: {e}", context={"operation": "bulk_message_sending"})
            
            # 최종 결과 요약
            print(f"\n📊 전송 완료!")
            print(f"[SUCCESS] 성공: {success_count}명")
            print(f"[ERROR] 실패: {fail_count}명")
            print(f"📈 성공률: {(success_count/len(usernames)*100):.1f}%")
            
            # tiktok_messages 테이블 업데이트 (message_id가 있는 경우)
            if message_id and self.db_session:
                try:
                    from datetime import datetime
                    # tiktok_messages 테이블 업데이트
                    message_record = self.db_session.query(TikTokMessage).filter(
                        TikTokMessage.id == message_id
                    ).first()
                    
                    if message_record:
                        message_record.send_status = 'completed'
                        message_record.is_complete = True
                        message_record.success_count = success_count
                        message_record.fail_count = fail_count
                        message_record.end_at = datetime.now()
                        self.db_session.commit()
                        print(f"[SUCCESS] tiktok_messages 테이블 업데이트 완료 (message_id: {message_id}, 성공: {success_count}, 실패: {fail_count})")
                except Exception as db_error:
                    print(f"[ERROR] tiktok_messages 테이블 업데이트 실패: {db_error}")
            
            return {
                "total": len(usernames),
                "success_count": success_count,
                "fail_count": fail_count,
                "success_rate": success_count / len(usernames) * 100 if len(usernames) > 0 else 0,
                "details": results
            }
        
        # Windows에서 이벤트 루프 정책 설정
        if os.name == 'nt':
            asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())
        
        try:
            # 새 이벤트 루프 실행
            result = asyncio.run(_send_bulk_messages())
            
            # 메시지 전송 완료 후 처리
            if message_id:
                # bulk 전송의 경우 전체 결과에서 성공 여부 판단
                overall_success = result.get('success', False) if isinstance(result, dict) else False
                self._complete_message_processing(message_id, overall_success)
            
            return result
        except Exception as e:
            # 에러 발생 시 start_at 롤백
            if message_id:
                print(f"No rollback needed for message ID {message_id}")
            return {"error": f"동기 실행 오류: {e}"}

    def load_message_templates(self, template_code: str = None):
        """
        DB에서 메시지 템플릿을 로드하여 캐싱
        
        Args:
            template_code: 템플릿 코드 (필수)
        """
        self.template_manager.load_message_templates(template_code)

    def _get_random_message_template(self):
        """
        캐시된 템플릿 데이터에서 랜덤하게 조합하여 메시지를 생성
        
        Returns:
            조합된 메시지 문자열
        """
        return self.template_manager.get_random_message_template()

    def upsert_message_log(self, tiktok_user_id: int, tiktok_message_id: int, message_text: str, result: str, result_text: str = None, tiktok_sender_id: int = None):
        """
        메시지 전송 로그를 데이터베이스에 기록합니다.
        
        Args:
            tiktok_user_id: TikTok 사용자 ID
            tiktok_message_id: TikTok 메시지 ID
            message_text: 전송한 메시지 내용
            result: 전송 결과 (success/failed)
            result_text: 전송 결과 상세 메시지
            tiktok_sender_id: 발신자 ID
        """
        TikTokMessageLogger.upsert_message_log(
            self.db_session, tiktok_user_id, tiktok_message_id, 
            message_text, result, result_text, tiktok_sender_id
        )

    def _update_message_count(self, message_id: int, is_success: bool) -> None:
        """
        메시지 전송 후 성공/실패 카운트를 실시간으로 업데이트
        
        Args:
            message_id: 메시지 ID
            is_success: 성공 여부 (True: 성공, False: 실패)
        """
        TikTokMessageCounter.update_message_count(self.db_session, message_id, is_success)

    def _check_and_mark_message_processing(self, message_id: int) -> Dict:
        """
        메시지 처리 중복 방지를 위한 체크 및 표시
        
        Args:
            message_id: 메시지 ID
            
        Returns:
            Dict: can_process 여부와 상태 정보
        """
        result = TikTokMessageProcessor.check_and_mark_message_processing(self.db_session, message_id)
        return result

    def _complete_message_processing(self, message_id: int, success: bool) -> None:
        """
        메시지 처리 완료 표시
        
        Args:
            message_id: 메시지 ID
            success: 성공 여부
        """
        TikTokMessageProcessor.complete_message_processing(self.db_session, message_id, success)

    # === AUTHENTICATION & SESSION ===
    def login_with_playwright(
        self,
        username: str,
        password: str,
        session_file_name: str | None = None
    ) -> Dict:
        """
        Playwright를 사용한 TikTok 로그인 및 세션 저장
        
        Args:
            username: TikTok 사용자명/이메일
            password: TikTok 비밀번호
            session_file_name: 세션 파일 저장 경로
        
        Returns:
            로그인 성공 여부 및 세션 파일 경로
        """
        print("[INFO] Playwright 기반 TikTok 로그인 시작...")
        
        # 세션 파일 경로 설정
        if not session_file_name:
            session_file_name = f"tiktok_auth_{username}.json"
        
        session_file_path = Path(session_file_name)
        session_file_path.parent.mkdir(parents=True, exist_ok=True)
        
        try:
            with SyncBrowserManager() as browser_manager:
                # 브라우저 초기화 (headless=False로 브라우저 창 표시)
                browser_manager.initialize(headless=False)
                page = browser_manager.page
                context = browser_manager.context
                
                # TikTok 로그인 페이지로 직접 이동
                login_url = "https://www.tiktok.com/login/phone-or-email/email"
                print(f"로그인 페이지로 이동: {login_url}")
                page.goto(login_url)
                
                # 페이지 로딩 대기
                page.wait_for_load_state('networkidle')
                time.sleep(random.uniform(3, 5))
                
                # 자연스러운 사용자 행동 시뮬레이션
                print("사용자 행동 시뮬레이션...")
                browser_manager.simulate_human_behavior_with_page()
                
                # 이메일 입력
                print("이메일 입력 중...")
                try:
                    username_input = page.locator('input[name="username"]').first
                    username_input.wait_for(state="visible", timeout=10000)
                    
                    # 클릭하고 천천히 입력
                    username_input.click()
                    time.sleep(random.uniform(0.5, 1))
                    username_input.fill("")  # 기존 내용 지우기
                    
                    # 한 글자씩 천천히 입력
                    for char in username:
                        username_input.type(char, delay=random.uniform(50, 150))
                    
                    print("[SUCCESS] 이메일 입력 완료")
                    
                except Exception as e:
                    print(f"[ERROR] 이메일 입력 실패: {e}")
                    return {"success": False, "message": f"이메일 입력 실패: {e}"}
                
                # 비밀번호 입력
                print("비밀번호 입력 중...")
                try:
                    password_input = page.locator('input[type="password"]').first
                    password_input.wait_for(state="visible", timeout=5000)
                    
                    password_input.click()
                    time.sleep(random.uniform(0.5, 1))
                    password_input.fill("")  # 기존 내용 지우기
                    
                    # 한 글자씩 천천히 입력
                    for char in password:
                        password_input.type(char, delay=random.uniform(50, 150))
                    
                    print("[SUCCESS] 비밀번호 입력 완료")
                    
                except Exception as e:
                    print(f"[ERROR] 비밀번호 입력 실패: {e}")
                    return {"success": False, "message": f"비밀번호 입력 실패: {e}"}
                
                time.sleep(random.uniform(1, 2))
                
                # 로그인 버튼 클릭
                print("로그인 버튼 클릭 중...")
                try:
                    login_button = page.locator('button[type="submit"]').first
                    login_button.wait_for(state="visible", timeout=5000)
                    
                    # 버튼 위로 마우스 이동 후 클릭
                    login_button.hover()
                    time.sleep(random.uniform(0.5, 1))
                    login_button.click()
                    
                    print("[SUCCESS] 로그인 버튼 클릭 완료")
                    
                except Exception as e:
                    print(f"[ERROR] 로그인 버튼 클릭 실패: {e}")
                    return {"success": False, "message": f"로그인 버튼 클릭 실패: {e}"}
                
                # 로그인 처리 대기
                print("로그인 처리 중... (최대 30초 대기)")
                time.sleep(random.uniform(5, 10))
                
                # CAPTCHA 또는 추가 인증 확인
                current_url = page.url
                if "verify" in current_url or "captcha" in current_url:
                    print("[WARNING] CAPTCHA 또는 추가 인증이 필요합니다.")
                    print("브라우저에서 직접 해결해주세요.")
                    input("인증 완료 후 Enter를 누르세요...")
                
                # 로그인 성공 확인
                try:
                    # 메인 페이지로 이동해서 로그인 상태 확인
                    page.goto("https://www.tiktok.com")
                    page.wait_for_load_state('networkidle')
                    
                    # 프로필 아이콘 확인 (로그인 상태 확인)
                    profile_icon = page.locator('[data-e2e="profile-icon"]')
                    profile_icon.wait_for(state="visible", timeout=15000)
                    
                    print("[SUCCESS] 로그인 성공 확인!")
                    
                    # Playwright의 storage_state를 사용하여 세션 저장
                    storage = context.storage_state()
                    
                    # 로컬에 JSON 파일로 저장
                    with open(session_file_path, 'w', encoding='utf-8') as f:
                        json.dump(storage, f, indent=4, ensure_ascii=False)
                    
                    print(f"[SUCCESS] 세션이 로컬에 '{session_file_path}'로 저장되었습니다.")
                    print(f"총 {len(storage.get('cookies', []))}개의 쿠키가 저장되었습니다.")
                    
                    # SSH를 통해 원격 서버에 업로드
                    upload_success = self.upload_file_via_ssh(
                        str(session_file_path),
                        os.path.basename(str(session_file_path))
                    )
                    
                    result = {
                        "success": True, 
                        "message": "로그인 및 세션 저장 성공", 
                        "local_file": str(session_file_path),
                        "cookies_count": len(storage.get('cookies', []))
                    }
                    
                    if upload_success:
                        result["remote_upload"] = True
                        result["remote_path"] = os.path.join(
                            os.getenv('SSH_REMOTE_PATH', '/home/ubuntu/instagram/storage/app/tiktok_sessions/'),
                            os.path.basename(str(session_file_path))
                        ).replace('\\', '/')
                    else:
                        result["remote_upload"] = False
                        result["message"] += " (원격 서버 업로드는 실패했지만 로컬에는 저장됨)"
                    
                    return result
                    
                except Exception as e:
                    print(f"[ERROR] 로그인 상태 확인 실패: {e}")
                    return {"success": False, "message": f"로그인 상태 확인 실패: {e}"}
                
        except Exception as e:
            print(f"[ERROR] 로그인 과정 오류: {e}")
            return {"success": False, "message": f"로그인 과정 오류: {e}"}

    # === UPLOAD MANAGEMENT ===
    def check_and_update_uploads(self, pending_requests: List[TikTokUploadRequest]) -> Dict:
        """
        업로드 요청을 확인하고 매칭되는 비디오를 찾아 정보를 업데이트합니다.
        
        Args:
            pending_requests: 처리할 업로드 요청 리스트
            
        Returns:
            처리 결과
        """
        checked_count = 0
        updated_count = 0
        results = []
        
        try:
            with SyncBrowserManager() as browser_manager:
                browser_manager.initialize(headless=False)
                page = browser_manager.page
                
                for request in pending_requests:
                    checked_count += 1
                    print(f"\n[{checked_count}/{len(pending_requests)}] Processing request ID: {request.id}")
                    
                    # request_tags를 공백으로 분리
                    if not request.request_tags:
                        print(f"  ⚠️ No tags for request ID: {request.id}")
                        continue
                        
                    tags = request.request_tags.split()
                    print(f"  🏷️ Tags to search: {tags}")
                    
                    # 해당 사용자의 비디오들 조회
                    videos = self.db_session.query(TikTokVideo).filter(
                        TikTokVideo.tiktok_user_id == request.tiktok_user_id
                    ).all()
                    
                    if not videos:
                        print(f"  ❌ No videos found for user ID: {request.tiktok_user_id}")
                        continue
                    
                    print(f"  📹 Found {len(videos)} videos for user")
                    
                    # 태그가 모두 포함된 비디오 찾기
                    matched_video = None
                    for video in videos:
                        if video.title:
                            # 모든 태그가 title에 포함되어 있는지 확인
                            if all(tag in video.title for tag in tags):
                                matched_video = video
                                print(f"  ✅ Matched video: {video.title[:50]}...")
                                break
                    
                    if not matched_video:
                        print(f"  ❌ No matching video found with tags: {tags}")
                        continue
                    
                    # 비디오 페이지 방문하여 상세 정보 추출
                    try:
                        print(f"  🌐 Visiting video URL: {matched_video.video_url}")
                        page.goto(matched_video.video_url, wait_until="networkidle")
                        time.sleep(random.uniform(3, 5))
                        
                        # posted_at 추출 (예: "2024-12-25" 형식 또는 "3일 전" 형식)
                        try:
                            date_element = page.query_selector('[data-e2e="browser-nickname"] span:last-child')
                            if date_element:
                                date_text = date_element.inner_text()
                                
                                # 먼저 상대적 날짜 파싱 시도
                                posted_at = TikTokDataParser.parse_relative_date(date_text)
                                
                                # 상대적 날짜 파싱이 실패하면 일반 날짜 파싱 시도
                                if posted_at is None:
                                    try:
                                        from dateutil import parser
                                        posted_at = parser.parse(date_text)
                                    except:
                                        # 둘 다 실패하면 None
                                        posted_at = None
                                
                                if posted_at:
                                    matched_video.posted_at = posted_at
                                    print(f"    📅 Posted at: {posted_at}")
                                else:
                                    print(f"    ⚠️ Could not parse date: {date_text}")
                        except Exception as e:
                            print(f"    ⚠️ Could not extract posted_at: {e}")
                        
                        # like_count 추출
                        try:
                            like_element = page.query_selector('[data-e2e="like-count"]')
                            if like_element:
                                like_text = like_element.inner_text()
                                matched_video.like_count = TikTokDataParser.parse_count(like_text)
                                print(f"    ❤️ Likes: {matched_video.like_count}")
                        except Exception as e:
                            print(f"    ⚠️ Could not extract like_count: {e}")
                        
                        # comment_count 추출
                        try:
                            comment_element = page.query_selector('[data-e2e="comment-count"]')
                            if comment_element:
                                comment_text = comment_element.inner_text()
                                matched_video.comment_count = TikTokDataParser.parse_count(comment_text)
                                print(f"    💬 Comments: {matched_video.comment_count}")
                        except Exception as e:
                            print(f"    ⚠️ Could not extract comment_count: {e}")
                        
                        # share_count 추출
                        try:
                            share_element = page.query_selector('[data-e2e="share-count"]')
                            if share_element:
                                share_text = share_element.inner_text()
                                matched_video.share_count = TikTokDataParser.parse_count(share_text)
                                print(f"    🔄 Shares: {matched_video.share_count}")
                        except Exception as e:
                            print(f"    ⚠️ Could not extract share_count: {e}")
                        
                        # 업로드 요청 업데이트
                        request.is_uploaded = True
                        request.upload_url = matched_video.video_url
                        request.upload_thumbnail_url = matched_video.thumbnail_url
                        request.uploaded_at = matched_video.posted_at  # 비디오의 게시일을 uploaded_at에 저장
                        request.tiktok_video_id = matched_video.id
                        
                        # DB 커밋
                        self.db_session.commit()
                        updated_count += 1
                        
                        results.append({
                            "request_id": request.id,
                            "video_id": matched_video.id,
                            "status": "updated",
                            "video_url": matched_video.video_url
                        })
                        
                        print(f"  ✅ Successfully updated request ID: {request.id}")
                        
                    except Exception as e:
                        print(f"  ❌ Error processing video: {e}")
                        self.db_session.rollback()
                        results.append({
                            "request_id": request.id,
                            "status": "error",
                            "error": str(e)
                        })
                        continue
                    
                    # 요청 간 대기
                    time.sleep(random.uniform(2, 4))
            
        except Exception as e:
            print(f"❌ Browser error: {e}")
            return {
                "success": False,
                "error": str(e),
                "checked_count": checked_count,
                "updated_count": updated_count
            }
        
        return {
            "success": True,
            "checked_count": checked_count,
            "updated_count": updated_count,
            "results": results
        }

    # === LEGACY METHODS (TO BE REMOVED LATER) ===
    def _upload_image_to_admin(self, file_path: str, username: str, image_type: str, record_id: int, table_type: str) -> Optional[str]:
        """
        로컬 이미지를 관리페이지에 업로드합니다.
        
        Args:
            file_path: 업로드할 로컬 파일 경로
            username: 사용자명
            image_type: 이미지 타입 (사용하지 않음, 호환성 위해 유지)
            record_id: 테이블 레코드 ID
            table_type: 테이블 타입 (user, video, repost_video)
            
        Returns:
            업로드된 이미지 URL 또는 None
        """
        admin_url = settings.ADMIN_URL
        if not admin_url:
            print("⚠️ ADMIN_URL이 설정되지 않았습니다.")
            return None
            
        try:
            upload_url = f"{admin_url.rstrip('/')}/api/tiktok/upload-image"
            
            # 파일명 생성
            filename = os.path.basename(file_path)
            
            # 멀티파트 폼 데이터 준비
            with open(file_path, 'rb') as f:
                files = {
                    'image': (filename, f, 'image/jpeg')
                }
                data = {
                    'table_type': table_type,
                    'tiktok_username': username,
                    'record_id': str(record_id)
                }
                
                response = requests.post(
                    upload_url,
                    files=files,
                    data=data,
                    timeout=30
                )
            
            if response.status_code == 200:
                result = response.json()
                uploaded_url = result.get('url')
                print(f"✅ 관리페이지 업로드 완료: {uploaded_url}")
                return uploaded_url
            else:
                print(f"⚠️ 관리페이지 업로드 실패: {response.status_code} - {response.text}")
                return None
                
        except Exception as e:
            print(f"⚠️ 관리페이지 업로드 실패: {e}")
            return None

    def _download_image(self, image_url: str, username: str, image_type: str = "thumbnail") -> Optional[str]:
        """
        이미지를 다운로드하고 로컬에 저장합니다.
        
        Args:
            image_url: 다운로드할 이미지 URL
            username: 사용자명 (디렉토리 생성용)
            image_type: 이미지 타입 (thumbnail, profile 등)
            
        Returns:
            로컬 이미지 경로 또는 None
        """
        if not image_url:
            return None
            
        try:
            # 사용자별 디렉토리 생성
            user_dir = self.image_base_dir / username
            user_dir.mkdir(exist_ok=True)
            
            # URL에서 파일명 생성 (해시 사용)
            url_hash = hashlib.md5(image_url.encode()).hexdigest()[:8]
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            
            # 확장자 추출
            parsed_url = urlparse(image_url)
            path_parts = parsed_url.path.split('.')
            extension = path_parts[-1] if len(path_parts) > 1 and path_parts[-1] in ['jpg', 'jpeg', 'png', 'gif', 'webp'] else 'jpg'
            
            # 파일명 생성
            filename = f"{image_type}_{timestamp}_{url_hash}.{extension}"
            file_path = user_dir / filename
            
            # 이미지 다운로드
            headers = {
                'User-Agent': TikTokBrowserConfig.USER_AGENT,
                'Referer': 'https://www.tiktok.com/'
            }
            
            response = requests.get(image_url, headers=headers, timeout=10)
            response.raise_for_status()
            
            # 파일 저장
            with open(file_path, 'wb') as f:
                f.write(response.content)
            
            print(f"✅ 이미지 다운로드 완료: {file_path}")
            return str(file_path)
            
        except Exception as e:
            print(f"⚠️ 이미지 다운로드 실패 ({image_url[:50]}...): {e}")
            return None

    def _upload_downloaded_image(self, local_path: str, username: str, record_id: int, table_type: str) -> Optional[str]:
        """
        다운로드된 로컬 이미지를 관리페이지에 업로드합니다.

        Args:
            local_path: 로컬 이미지 파일 경로
            username: 사용자명
            record_id: 테이블 레코드 ID
            table_type: 테이블 타입 (user, video, repost_video)

        Returns:
            업로드된 이미지 URL 또는 None
        """
        if not local_path or not os.path.exists(local_path):
            return None

        return self._upload_image_to_admin(local_path, username, "image", record_id, table_type)

    def collect_user_from_video(self, video_url: str) -> Optional[Dict]:
        """
        비디오 페이지에서 사용자 정보를 수집합니다.

        Args:
            video_url: TikTok 비디오 URL

        Returns:
            사용자 정보 딕셔너리 또는 None
        """
        # URL에서 사용자명 추출
        import re
        username_match = re.search(r'@([^/]+)', video_url)
        if not username_match:
            print(f"❌ URL에서 사용자명을 추출할 수 없습니다: {video_url}")
            return None

        username = username_match.group(1)
        profile_url = f"https://www.tiktok.com/@{username}"
        print(f"👤 추출된 사용자명: {username}")
        print(f"🔗 프로필 URL: {profile_url}")

        async def _collect_user_async():
            """내부 비동기 사용자 수집 함수"""
            try:
                async with AsyncBrowserManager() as browser_manager:
                    # 브라우저 초기화
                    await browser_manager.initialize(headless=False, session_file=None)
                    page = browser_manager.page

                    # 프로필 페이지로 직접 이동
                    print(f"👤 프로필 페이지로 직접 이동: {profile_url}")
                    await page.goto(profile_url, wait_until="domcontentloaded")

                    # 페이지 로딩 대기 (초기 대기)
                    print("⏳ 페이지 초기 로딩 대기 중...")
                    await page.wait_for_timeout(3000)  # 3초 대기

                    # 패스키 모달 처리
                    await page.wait_for_timeout(2000)  # 모달이 나타날 시간을 주기 위해 짧은 대기
                    await browser_manager.handle_passkey_modal()

                    # 추가 페이지 로딩 대기
                    print("⏳ 프로필 페이지 로딩 대기 중 (10초)...")
                    await page.wait_for_timeout(10000)  # 10초 대기

                    # 프로필 요소가 로드되었는지 확인
                    try:
                        await page.wait_for_selector('[data-e2e="user-title"]', timeout=5000)
                        print("✅ 프로필 페이지 로드 완료")
                    except:
                        print("⚠️ 프로필 페이지 요소 확인 실패, 계속 진행...")

                    # 추가 안정화 대기
                    await page.wait_for_timeout(3000)

                    # 사용자 정보 수집
                    user_data = {}

                    # username
                    username_element = await page.query_selector('[data-e2e="user-title"]')
                    if username_element:
                        username_text = await username_element.text_content()
                        user_data['username'] = username_text.strip() if username_text else None

                    # nickname
                    nickname_element = await page.query_selector('[data-e2e="user-subtitle"]')
                    if nickname_element:
                        nickname_text = await nickname_element.text_content()
                        user_data['nickname'] = nickname_text.strip() if nickname_text else None

                    # followers
                    followers_element = await page.query_selector('[data-e2e="followers-count"]')
                    if followers_element:
                        followers_text = await followers_element.text_content()
                        user_data['followers'] = TikTokDataParser.parse_follower_count(followers_text)

                    # bio
                    bio_element = await page.query_selector('[data-e2e="user-bio"]')
                    if bio_element:
                        bio_text = await bio_element.text_content()
                        user_data['bio'] = bio_text.strip() if bio_text else None

                    # profile image
                    avatar_element = await page.query_selector('[data-e2e="user-avatar"] img')
                    if avatar_element:
                        profile_image = await avatar_element.get_attribute('src')
                        user_data['profile_image'] = profile_image

                        # 프로필 이미지 다운로드
                        if profile_image and user_data.get('username'):
                            local_image_path = self._download_image(
                                profile_image,
                                user_data['username'],
                                'profile'
                            )
                            if local_image_path:
                                print(f"✅ 프로필 이미지 저장: {local_image_path}")
                                # 로컬 이미지 경로를 user_data에 저장
                                user_data['local_profile_image_path'] = local_image_path

                    # profile URL
                    user_data['profile_url'] = page.url

                    print(f"✅ 사용자 정보 수집 완료: {user_data.get('username', 'Unknown')}")
                    return user_data

            except Exception as e:
                print(f"❌ 사용자 정보 수집 실패: {e}")
                import traceback
                traceback.print_exc()
                return None

        # 동기 함수에서 비동기 함수 실행
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        try:
            return loop.run_until_complete(_collect_user_async())
        finally:
            loop.close()

    def save_collected_user_with_upload(self, user_data: Dict, repost_video_id: int = None) -> Dict:
        """
        수집된 사용자 데이터를 저장하고 프로필 이미지를 관리자 페이지에 업로드합니다.

        Args:
            user_data: 수집된 사용자 데이터
            repost_video_id: 리포스트 비디오 ID (is_checked 업데이트용)

        Returns:
            처리 결과
        """
        try:
            if not user_data or not user_data.get('username'):
                return {"success": False, "message": "Invalid user data"}

            username = user_data['username']

            # 리포스트 비디오에서 브랜드 계정 정보 가져오기
            brand_account_id = None
            brand_name = None
            if repost_video_id:
                from app.models.tiktok import TikTokBrandAccount
                repost_video = self.db_session.query(TikTokRepostVideo).filter(
                    TikTokRepostVideo.id == repost_video_id
                ).first()
                if repost_video:
                    brand_account_id = repost_video.tiktok_brand_account_id
                    # 브랜드 계정에서 brand_name 가져오기
                    brand_account = self.db_session.query(TikTokBrandAccount).filter(
                        TikTokBrandAccount.id == brand_account_id
                    ).first()
                    if brand_account:
                        brand_name = brand_account.brand_name

            # 사용자 정보 저장 또는 업데이트
            existing_user = self.db_session.query(TikTokUser).filter(
                TikTokUser.username == username
            ).first()

            # keyword 필드 업데이트 로직
            def update_keyword(current_keyword: str) -> str:
                """keyword 필드에 '브랜드계정'과 브랜드명을 추가"""
                keywords_to_add = ['브랜드계정']
                if brand_name:
                    keywords_to_add.append(brand_name)

                if not current_keyword:
                    return ','.join(keywords_to_add)

                # 현재 keyword를 콤마로 분리
                existing_keywords = [k.strip() for k in current_keyword.split(',')]

                # 새로운 키워드 추가 (중복 제거)
                for kw in keywords_to_add:
                    if kw not in existing_keywords:
                        existing_keywords.append(kw)

                return ','.join(existing_keywords)

            user_record = None
            if not existing_user:
                # 새 사용자 생성 (profile_image는 제외)
                user_data_for_create = {k: v for k, v in user_data.items()
                                       if k not in ['profile_image', 'local_profile_image_path']}
                new_user = TikTokUser.from_scrape_data(user_data_for_create)

                # keyword 업데이트
                new_user.keyword = update_keyword(new_user.keyword)

                self.db_session.add(new_user)
                self.db_session.commit()  # 즉시 커밋하여 락 해제
                user_record = new_user
                print(f"✅ 새 사용자 생성: {username}, keyword: {new_user.keyword}")
            else:
                # 기존 사용자 업데이트
                for key, value in user_data.items():
                    if hasattr(existing_user, key) and value is not None and key not in ['profile_image', 'local_profile_image_path', 'keyword']:
                        setattr(existing_user, key, value)

                # keyword 업데이트
                existing_user.keyword = update_keyword(existing_user.keyword)

                self.db_session.commit()  # 먼저 커밋하여 락 해제
                user_record = existing_user
                print(f"🔄 기존 사용자 업데이트: {username}, keyword: {existing_user.keyword}")

            # 프로필 이미지를 관리자 페이지에 업로드
            local_image_path = user_data.get('local_profile_image_path')
            uploaded_url = None

            if local_image_path and user_record:
                from app.services.tiktok_utils import TikTokImageUtils
                from app.core.config import settings

                uploaded_url = TikTokImageUtils.upload_downloaded_image(
                    local_image_path, username, user_record.id, "user", settings.ADMIN_URL
                )

                # 이미지 URL 업데이트 (별도 세션 사용)
                if uploaded_url or local_image_path:
                    profile_image_value = uploaded_url if uploaded_url else local_image_path

                    try:
                        from sqlalchemy import text
                        from app.core.database import SessionLocal

                        with SessionLocal() as img_session:
                            img_sql = text("""
                                UPDATE tiktok_users
                                SET profile_image = :profile_image, updated_at = NOW()
                                WHERE id = :user_id
                            """)
                            img_session.execute(img_sql, {
                                'profile_image': profile_image_value,
                                'user_id': user_record.id
                            })
                            img_session.commit()

                            if uploaded_url:
                                print(f" 프로필 이미지 관리페이지 업로드 완료: user ID {user_record.id}, URL: {uploaded_url}")
                            else:
                                print(f" 로컬 이미지 경로 저장: {username} -> {local_image_path}")

                    except Exception as e:
                        print(f"❗ 프로필 이미지 업데이트 실패: {e}")
                        # 이미지 업데이트 실패는 무시하고 계속

            # 리포스트 비디오 확인 상태 업데이트
            if repost_video_id:
                try:
                    from sqlalchemy import text
                    from app.core.database import SessionLocal

                    with SessionLocal() as check_session:
                        check_sql = text("""
                            UPDATE tiktok_repost_videos
                            SET is_checked = 'Y', updated_at = NOW()
                            WHERE id = :video_id
                        """)
                        check_session.execute(check_sql, {'video_id': repost_video_id})
                        check_session.commit()
                        print(f"✅ 리포스트 비디오 {repost_video_id} 확인 상태 업데이트 완료")
                except Exception as e:
                    print(f"❗ 리포스트 비디오 확인 상태 업데이트 실패: {e}")
                    # 실패해도 계속 진행

            return {
                "success": True,
                "username": username,
                "user_id": user_record.id,
                "profile_uploaded": bool(local_image_path and uploaded_url)
            }

        except Exception as e:
            self.db_session.rollback()
            print(f"❌ 사용자 데이터 저장 중 오류: {e}")
            return {"success": False, "message": str(e)}

    def collect_multiple_users_from_videos(self, video_data_list: List[Dict], user_agent: Optional[str] = None, session_file: Optional[str] = None) -> Dict:
        """
        여러 비디오에서 사용자 정보를 수집합니다. (브라우저 재사용)

        Args:
            video_data_list: [{"video_url": str, "video_id": int, "country": str}, ...]
            user_agent: 사용할 User-Agent 문자열 (선택사항)
            session_file: 사용할 세션 파일 경로 (선택사항)

        Returns:
            수집 결과
        """
        async def _collect_multiple_users_async():
            processed_count = 0
            collected_users = []
            failed_videos = []

            try:
                async with AsyncBrowserManager() as browser_manager:
                    # 브라우저 초기화 (한 번만) - user_agent, session_file 전달
                    await browser_manager.initialize(headless=False, session_file=session_file, user_agent=user_agent)
                    page = browser_manager.page

                    for video_data in video_data_list:
                        video_url = video_data.get('video_url')
                        video_id = video_data.get('video_id')
                        country = video_data.get('country')

                        try:
                            # URL에서 사용자명 추출
                            import re
                            username_match = re.search(r'@([^/]+)', video_url)
                            if not username_match:
                                print(f"❌ URL에서 사용자명을 추출할 수 없습니다: {video_url}")
                                failed_videos.append(video_id)
                                continue

                            username = username_match.group(1)
                            profile_url = f"https://www.tiktok.com/@{username}"
                            print(f"👤 추출된 사용자명: {username}")

                            # 프로필 페이지로 직접 이동
                            print(f"🔗 프로필 페이지로 직접 이동: {profile_url}")
                            await page.goto(profile_url, wait_until="domcontentloaded")

                            # 패스키 모달 처리
                            await page.wait_for_timeout(2000)  # 모달이 나타날 시간을 주기 위해 짧은 대기
                            await browser_manager.handle_passkey_modal()

                            # 추가 페이지 로딩 대기
                            print("⏳ 프로필 페이지 로딩 대기 중 (10초)...")
                            await page.wait_for_timeout(10000)  # 10초 대기

                            # 프로필 요소가 로드되었는지 확인
                            try:
                                await page.wait_for_selector('[data-e2e="user-title"]', timeout=5000)
                                print("✅ 프로핀 페이지 로드 완료")
                            except:
                                print("⚠️ 프로필 페이지 요소 확인 실패, 계속 진행...")

                            # 추가 안정화 대기
                            await page.wait_for_timeout(3000)

                            # 사용자 정보 수집
                            user_data = {}

                            # username
                            username_element = await page.query_selector('[data-e2e="user-title"]')
                            if username_element:
                                username_text = await username_element.text_content()
                                user_data['username'] = username_text.strip() if username_text else None

                            # nickname
                            nickname_element = await page.query_selector('[data-e2e="user-subtitle"]')
                            if nickname_element:
                                nickname_text = await nickname_element.text_content()
                                user_data['nickname'] = nickname_text.strip() if nickname_text else None

                            # followers
                            followers_element = await page.query_selector('[data-e2e="followers-count"]')
                            if followers_element:
                                followers_text = await followers_element.text_content()
                                user_data['followers'] = TikTokDataParser.parse_follower_count(followers_text)

                            # bio
                            bio_element = await page.query_selector('[data-e2e="user-bio"]')
                            if bio_element:
                                bio_text = await bio_element.text_content()
                                user_data['bio'] = bio_text.strip() if bio_text else None

                            # profile image
                            avatar_element = await page.query_selector('[data-e2e="user-avatar"] img')
                            if avatar_element:
                                profile_image = await avatar_element.get_attribute('src')
                                user_data['profile_image'] = profile_image

                                # 프로필 이미지 다운로드
                                if profile_image and user_data.get('username'):
                                    local_image_path = self._download_image(
                                        profile_image,
                                        user_data['username'],
                                        'profile'
                                    )
                                    if local_image_path:
                                        print(f" 프로필 이미지 저장: {local_image_path}")
                                        user_data['local_profile_image_path'] = local_image_path

                            # profile URL
                            user_data['profile_url'] = page.url

                            # country 값 추가
                            if country:
                                user_data['country'] = country

                            print(f" 사용자 정보 수집 완료: {user_data.get('username', 'Unknown')}")

                            # 사용자 정보 저장
                            if user_data and user_data.get('username'):
                                save_result = self.save_collected_user_with_upload(user_data, video_id)
                                if save_result and save_result.get('success'):
                                    collected_users.append(user_data['username'])
                                    processed_count += 1
                                else:
                                    failed_videos.append(video_id)
                            else:
                                failed_videos.append(video_id)

                        except Exception as e:
                            print(f" 비디오 {video_id} 처리 실패: {e}")
                            failed_videos.append(video_id)
                            continue

            except Exception as e:
                print(f" 브라우저 오류: {e}")
                import traceback
                traceback.print_exc()

            return {
                "processed": processed_count,
                "collected_users": collected_users,
                "failed_videos": failed_videos
            }

        # 비동기 함수를 동기적으로 실행
        import nest_asyncio
        nest_asyncio.apply()

        loop = asyncio.new_event_loop()
        try:
            return loop.run_until_complete(_collect_multiple_users_async())
        finally:
            loop.close()
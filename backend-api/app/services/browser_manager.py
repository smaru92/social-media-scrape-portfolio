"""
TikTok 브라우저 관리 모듈

모든 브라우저 관련 로직을 통합 관리하는 클래스
- 브라우저 설정 표준화
- 프로필 페이지 이동 로직 통합
- 세션 관리 통합
- CAPTCHA 탐지 및 처리
"""

import os
import time
import random
from typing import Optional, Dict, Any
from playwright.async_api import async_playwright, Browser, BrowserContext, Page
from playwright.sync_api import sync_playwright, Browser as SyncBrowser, BrowserContext as SyncBrowserContext, Page as SyncPage


class TikTokBrowserConfig:
    """TikTok 브라우저 설정 상수"""
    
    # 브라우저 실행 인자 (최소한의 설정만 사용)
    BROWSER_ARGS = [
        "--disable-blink-features=AutomationControlled",
        "--no-sandbox",
        "--disable-dev-shm-usage",
        "--window-size=1920,1080",
        "--start-maximized"
    ]
    
    # User Agent (Chrome 131, 2025년 1월 최신)
    USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
    
    # 뷰포트 설정 (일반적인 데스크톱 크기)
    VIEWPORT = {"width": 1920, "height": 1080}
    
    # 브라우저 컨텍스트 설정
    CONTEXT_CONFIG = {
        "user_agent": USER_AGENT,
        "viewport": VIEWPORT,
        "device_scale_factor": 1,
        "is_mobile": False,
        "has_touch": False
    }
    
    # TikTok URL
    TIKTOK_MAIN_URL = "https://www.tiktok.com/?lang=ko-KR"
    
    # 대기 시간 범위
    WAIT_TIMES = {
        "page_load": (5000, 10000),
        "scroll": (1000, 3000),
        "navigation": (3000, 5000),
        "interaction": (500, 1000)
    }


class AsyncBrowserManager:
    """비동기 브라우저 관리 클래스"""
    
    def __init__(self):
        self.playwright = None
        self.browser: Optional[Browser] = None
        self.context: Optional[BrowserContext] = None
        self.page: Optional[Page] = None
    
    async def __aenter__(self):
        """컨텍스트 매니저 진입"""
        return self
    
    async def __aexit__(self, exc_type, exc_val, exc_tb):
        """컨텍스트 매니저 종료"""
        await self.close()
    
    async def initialize(self, headless: bool = False, session_file: Optional[str] = None, user_agent: Optional[str] = None):
        """브라우저 초기화"""
        self.playwright = await async_playwright().start()

        # 브라우저 실행
        self.browser = await self.playwright.chromium.launch(
            headless=headless,
            args=TikTokBrowserConfig.BROWSER_ARGS
        )

        # 컨텍스트 생성
        context_config = TikTokBrowserConfig.CONTEXT_CONFIG.copy()

        # 사용자 정의 User-Agent가 있으면 사용
        if user_agent:
            context_config["user_agent"] = user_agent

        if session_file and os.path.exists(session_file):
            context_config["storage_state"] = session_file

        self.context = await self.browser.new_context(**context_config)
        
        # 페이지 생성
        self.page = await self.context.new_page()

        # 봇 탐지 회피 스크립트는 오히려 캡챠를 유발하므로 사용하지 않음
        # TikTok은 스크립트 injection을 감지하는 것으로 보임

        print(f"✅ 브라우저 초기화 완료 (세션: {'사용' if session_file else '미사용'})")
    
    async def navigate_to_main_page(self):
        """TikTok 메인 페이지로 이동"""
        if not self.page:
            raise RuntimeError("브라우저가 초기화되지 않았습니다.")

        print("🏠 TikTok 메인 페이지로 이동...")
        await self.page.goto(TikTokBrowserConfig.TIKTOK_MAIN_URL, wait_until="load")
        await self.page.wait_for_timeout(random.uniform(*TikTokBrowserConfig.WAIT_TIMES["page_load"]))

        # 패스키 모달 처리
        await self.page.wait_for_timeout(2000)  # 모달이 나타날 시간을 주기 위해 짧은 대기
        await self.handle_passkey_modal()

        # 사람처럼 스크롤 시뮬레이션
        await self.simulate_human_behavior()
    
    async def auto_scroll_async(self, scrolls: int = 5, delay_range: tuple = (2, 4)) -> None:
        """
        페이지 자동 스크롤 (async 버전)

        Args:
            scrolls: 스크롤 횟수
            delay_range: 스크롤 간 딜레이 범위 (초)
        """
        if not self.page:
            return
        
        for i in range(scrolls):
            await self.page.evaluate("window.scrollTo(0, document.body.scrollHeight);")
            delay = random.uniform(*delay_range)
            print(f"→ 스크롤 {i+1}/{scrolls} (딜레이 {delay:.1f}s)")
            await self.page.wait_for_timeout(delay * 1000)
    
    async def handle_passkey_modal(self) -> bool:
        """
        패스키 설정 모달을 감지하고 닫기

        Returns:
            bool: 모달을 처리했으면 True, 모달이 없었으면 False
        """
        if not self.page:
            return False

        try:
            # 패스키 설정 모달이 나타나면 닫기
            passkey_modal = await self.page.query_selector('[role="dialog"]')
            if passkey_modal:
                # 닫기 버튼 찾기 - 다양한 셀렉터 시도
                close_button = None

                # X 버튼 또는 Close 버튼 찾기
                selectors = [
                    '[role="dialog"] button[aria-label*="Close"]',
                    '[role="dialog"] button[aria-label*="close"]',
                    '[role="dialog"] svg[width="24"]',  # X 아이콘
                    '[role="dialog"] button:has-text("나중에")',
                    '[role="dialog"] button:has-text("Skip")',
                    '[role="dialog"] button:has-text("Not now")',
                    'button:has-text("건너뛰기")',
                    'button:has-text("Later")',
                    '[role="dialog"] [aria-label="Close"]',
                    '[role="dialog"] button[type="button"]'  # 가장 마지막 폴백
                ]

                for selector in selectors:
                    try:
                        close_button = await self.page.query_selector(selector)
                        if close_button:
                            # 버튼이 클릭 가능한지 확인
                            is_visible = await close_button.is_visible()
                            if is_visible:
                                await close_button.click()
                                print("✅ 패스키 설정 모달을 닫았습니다")
                                await self.page.wait_for_timeout(1000)
                                return True
                    except:
                        continue

                if not close_button:
                    # ESC 키로 모달 닫기 시도
                    await self.page.keyboard.press('Escape')
                    print("ℹ️ ESC 키로 모달 닫기 시도")
                    await self.page.wait_for_timeout(1000)
                    return True

            return False

        except Exception as e:
            # 모달 처리 실패해도 계속 진행
            print(f"⚠️ 패스키 모달 처리 중 오류 (무시): {e}")
            return False

    async def simulate_human_behavior(self):
        """사람처럼 행동 시뮬레이션"""
        if not self.page:
            return
        
        print("📜 사람처럼 행동 시뮬레이션...")
        await self.page.mouse.wheel(0, random.uniform(500, 1500))
        await self.page.wait_for_timeout(random.uniform(2000, 4000))
        await self.page.mouse.wheel(0, random.uniform(300, 800))
        await self.page.wait_for_timeout(random.uniform(*TikTokBrowserConfig.WAIT_TIMES["scroll"]))
    
    async def navigate_to_profile(self, username: str) -> bool:
        """프로필 페이지로 안전하게 이동"""
        if not self.page:
            return False
        
        try:
            profile_url = f"https://www.tiktok.com/@{username}"
            print(f"사용자 프로필로 이동: {profile_url}")

            await self.page.goto(profile_url, wait_until="networkidle", timeout=60000)
            await self.page.wait_for_timeout(random.uniform(*TikTokBrowserConfig.WAIT_TIMES["page_load"]))
            
            # CAPTCHA 확인
            if await self.is_captcha_present():
                await self.page.screenshot(path=f'debug_{username}_captcha.png')
                print(f"CAPTCHA가 발생했습니다. '{username}' 처리를 건너뜁니다.")
                return False
            
            print(f"✅ '{username}' 프로필 페이지 로드 완료")
            return True
            
        except Exception as e:
            print(f"프로필 페이지 이동 중 오류: {e}")
            return False
    
    async def is_captcha_present(self) -> bool:
        """CAPTCHA 존재 여부 확인"""
        if not self.page:
            return False
        
        return "verify" in self.page.url or "captcha" in self.page.url
    
    async def check_login_status(self) -> bool:
        """로그인 상태 확인"""
        if not self.page:
            return False
        
        try:
            await self.page.wait_for_selector('[data-e2e="nav-profile"]', timeout=10000)
            print("✅ 로그인 상태 확인됨")
            return True
        except:
            print("⚠️ 로그인 세션을 확인할 수 없습니다.")
            return False
    
    async def send_direct_message(self, username: str, message: str) -> dict:
        """특정 사용자에게 다이렉트 메시지 전송"""
        if not self.page:
            return {"success": False, "message": "브라우저가 초기화되지 않았습니다.", "sent_message": None}
        
        try:
            profile_url = f"https://www.tiktok.com/@{username}"
            print(f"--- {username} 사용자에게 DM 발송 시도 ---")
            print(f"    생성된 메시지: {message[:50]}..." if len(message) > 50 else f"    생성된 메시지: {message}")
            
            # 프로필 페이지로 이동
            await self.page.goto(profile_url, wait_until="networkidle", timeout=60000)
            await self.page.wait_for_timeout(random.uniform(5000, 10000))
            
            # CAPTCHA 감지
            if await self.is_captcha_present():
                await self.page.screenshot(path=f'debug_{username}_captcha.png')
                return {"success": False, "message": "CAPTCHA 발생", "sent_message": None}
            
            # 메시지 버튼 찾기
            message_button_selector = 'button[data-e2e="message-button"]'
            message_button = self.page.locator(message_button_selector).first
            
            try:
                await message_button.wait_for(state="visible", timeout=10000)
            except Exception:
                await self.page.screenshot(path=f'debug_{username}_no_button.png')
                print(f"[ERROR] {username} 사용자에게 메시지를 보낼 수 없습니다 (메시지 버튼 없음).")
                return {"success": False, "message": "메시지 버튼 없음", "sent_message": None}
            
            # 메시지 버튼 클릭 (인간처럼)
            button_handle = await message_button.element_handle()
            if button_handle:
                print(f"'{username}'의 메시지 버튼을 사람처럼 클릭합니다.")
                box = await button_handle.bounding_box()
                if box:
                    x = box['x'] + box['width'] / 2
                    y = box['y'] + box['height'] / 2
                    await self.page.mouse.move(x, y, steps=random.randint(20, 30))
                    await self.page.wait_for_timeout(random.uniform(500, 1000))
                    await self.page.mouse.click(x, y)
                    await self.page.wait_for_timeout(random.uniform(2000, 3000))
                else:
                    return {"success": False, "message": "버튼 위치 정보 없음", "sent_message": None}
            else:
                return {"success": False, "message": "메시지 버튼 요소 없음", "sent_message": None}
            
            # iframe 체크
            frames = self.page.frames
            print(f"페이지의 프레임 수: {len(frames)}")
            
            # 메시지 입력창 기다리기 (다양한 선택자 시도)
            message_input_selectors = [
                'div[data-e2e="message-input-area"]',  # 가장 정확한 선택자
                '[data-e2e="message-input-area"]',  # div 태그 제거
                'div[contenteditable="true"]',  # div + contenteditable
                '[contenteditable="true"]',  # contenteditable 속성만
                '.DraftEditor-root',  # DraftJS 에디터 클래스
                '.public-DraftEditor-content',  # DraftJS 콘텐츠 클래스
            ]
            
            message_input = None
            target_frame = self.page  # 기본은 메인 페이지
            
            # 먼저 메인 페이지에서 찾기
            for selector in message_input_selectors:
                try:
                    elements = await self.page.query_selector_all(selector)
                    if elements:
                        message_input = self.page.locator(selector).first
                        if await message_input.is_visible():
                            print(f"메시지 입력창 발견됨 (메인): {selector}")
                            break
                        else:
                            message_input = None
                except:
                    continue
            
            # iframe 내부도 확인
            if not message_input and len(frames) > 1:
                print("iframe 내부에서 메시지 입력창 검색 중...")
                for i, frame in enumerate(frames):
                    if i == 0:  # 메인 프레임은 이미 확인했음
                        continue
                    try:
                        for selector in message_input_selectors[:2]:  # 주요 선택자만
                            elements = await frame.query_selector_all(selector)
                            if elements:
                                print(f"  ✓ iframe {i}에서 {selector} 발견")
                                message_input = frame.locator(selector).first
                                target_frame = frame  # iframe을 타겟으로 설정
                                break
                        if message_input:
                            break
                    except:
                        continue
            
            if not message_input:
                await self.page.screenshot(path=f'debug_{username}_no_input.png')
                return {"success": False, "message": "메시지 입력창을 찾을 수 없습니다.", "sent_message": None}
            
            await self.page.wait_for_timeout(random.uniform(1500, 2500))
            
            # 메시지 입력
            print("메시지 입력란을 클릭합니다...")
            await message_input.click()
            await self.page.wait_for_timeout(random.uniform(500, 1000))
            
            print("메시지를 사람처럼 입력합니다...")
            # 사람처럼 천천히 타이핑
            for char in message:
                await message_input.type(char, delay=random.uniform(100, 200))
            
            await self.page.wait_for_timeout(random.uniform(1000, 1500))
            
            # 전송 버튼 찾기 (iframe 고려)
            send_button_selector = 'svg[data-e2e="message-send"]'
            send_button = None
            
            # 먼저 타겟 프레임(메인 또는 iframe)에서 찾기
            try:
                send_button = target_frame.locator(send_button_selector)
                if await send_button.count() > 0:
                    print(f"전송 버튼을 {'iframe' if target_frame != self.page else '메인 페이지'}에서 발견")
                else:
                    send_button = None
            except:
                send_button = None
            
            # 못 찾았으면 모든 프레임에서 다시 시도
            if not send_button or await send_button.count() == 0:
                print("전송 버튼을 다른 프레임에서 검색 중...")
                for frame in frames:
                    try:
                        temp_button = frame.locator(send_button_selector)
                        if await temp_button.count() > 0:
                            send_button = temp_button
                            print(f"전송 버튼을 프레임에서 발견")
                            break
                    except:
                        continue
            
            if send_button and await send_button.is_enabled():
                print("메시지 전송 버튼 위로 마우스를 이동합니다...")
                await send_button.hover()
                await self.page.wait_for_timeout(random.uniform(600, 1200))
                
                print("메시지를 전송합니다...")
                await send_button.click()
                await self.page.wait_for_timeout(random.uniform(2500, 4000))
                
                print(f"[SUCCESS] '{username}'에게 메시지 전송 완료!")
                return {"success": True, "message": "메시지 전송 성공", "sent_message": message}
            else:
                await self.page.screenshot(path=f'debug_{username}_send_error.png')
                return {"success": False, "message": "전송 버튼을 찾을 수 없거나 비활성화됨", "sent_message": None}
            
        except Exception as e:
            print(f"[ERROR] {username} 메시지 전송 중 오류: {e}")
            return {"success": False, "message": f"오류: {str(e)}", "sent_message": None}
    
    async def take_screenshot(self, path: str):
        """스크린샷 촬영"""
        if not self.page:
            return
        
        await self.page.screenshot(path=path)
    
    async def wait_for_login_status(self) -> bool:
        """로그인 상태 대기 및 확인"""
        if not self.page:
            return False
        
        try:
            await self.page.wait_for_selector('[data-e2e="nav-profile"]', timeout=10000)
            return True
        except:
            return False
    
    async def navigate_to_search_page(self, keyword: str):
        """TikTok 검색 페이지로 이동"""
        if not self.page:
            return
        
        search_url = f"https://www.tiktok.com/search/user?q={keyword}"
        await self.page.goto(search_url, wait_until="load")
        await self.page.wait_for_timeout(5000)
    
    async def wait_for_video_containers(self, timeout: int = 10000):
        """비디오 컨테이너 로딩 대기"""
        if not self.page:
            return
        
        try:
            await self.page.wait_for_selector('[id^="column-item-video-container-"]', timeout=timeout)
            await self.page.wait_for_timeout(random.uniform(3000, 5000))
        except:
            print("⚠️ 비디오 컨테이너를 찾는 중 타임아웃. 계속 진행합니다...")
    
    async def get_video_containers(self):
        """비디오 컨테이너 요소들 조회"""
        if not self.page:
            return []
        
        containers = await self.page.query_selector_all('[id^="column-item-video-container-"]')
        video_containers = []
        
        for container in containers:
            first_link = await container.query_selector('a')
            if first_link:
                video_containers.append(first_link)
        
        # 대체 방법: 직접 ID로 찾기
        if len(video_containers) == 0:
            for i in range(100):  # 최대 100개까지 확인
                container = await self.page.query_selector(f'#column-item-video-container-{i}')
                if container:
                    first_link = await container.query_selector('a')
                    if first_link:
                        video_containers.append(first_link)
                else:
                    # 연속된 번호가 없으면 중단 (처음 몇 개는 스킵 가능)
                    if i > 10 and len(video_containers) > 0:
                        break
        
        return video_containers

    async def close(self):
        """브라우저 종료"""
        if self.browser:
            await self.browser.close()
        if self.playwright:
            await self.playwright.stop()
        print("🔚 브라우저 종료 완료")


class SyncBrowserManager:
    """동기 브라우저 관리 클래스 (레거시 호환용)"""
    
    def __init__(self):
        self.playwright = None
        self.browser: Optional[SyncBrowser] = None
        self.context: Optional[SyncBrowserContext] = None
        self.page: Optional[SyncPage] = None
    
    def __enter__(self):
        """컨텍스트 매니저 진입"""
        self.initialize()
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb):
        """컨텍스트 매니저 종료"""
        self.close()
    
    def initialize(self, headless: bool = False, session_file: Optional[str] = None):
        """브라우저 초기화"""
        self.playwright = sync_playwright().start()
        
        # 브라우저 실행
        self.browser = self.playwright.chromium.launch(
            headless=headless,
            args=TikTokBrowserConfig.BROWSER_ARGS
        )
        
        # 컨텍스트 생성
        context_config = TikTokBrowserConfig.CONTEXT_CONFIG.copy()
        if session_file and os.path.exists(session_file):
            context_config["storage_state"] = session_file
        
        self.context = self.browser.new_context(**context_config)
        
        # 페이지 생성
        self.page = self.context.new_page()

        # 봇탐지에 걸리는 옵션
        # await self.page.add_init_script("Object.defineProperty(navigator, 'webdriver', {get: () => undefined})")
        
        print(f"✅ 브라우저 초기화 완료 (세션: {'사용' if session_file else '미사용'})")
    
    def simulate_human_behavior_with_page(self) -> None:
        """자연스러운 사용자 행동 시뮬레이션"""
        if not self.page:
            return
        
        try:
            # 마우스 움직임 시뮬레이션
            viewport = self.page.viewport_size
            for _ in range(3):
                x = random.randint(100, viewport['width'] - 100)
                y = random.randint(100, viewport['height'] - 100)
                self.page.mouse.move(x, y)
                time.sleep(random.uniform(0.5, 1.0))
            
            # 스크롤 시뮬레이션
            scroll_amounts = [200, -100, 300, -200]
            for amount in scroll_amounts:
                self.page.mouse.wheel(0, amount)
                time.sleep(random.uniform(0.8, 1.5))
            
        except Exception as e:
            print(f"[WARNING] 행동 시뮬레이션 오류: {e}")
            # 실패해도 계속 진행
    
    def navigate_to_profile(self, username: str) -> bool:
        """프로필 페이지로 안전하게 이동"""
        if not self.page:
            return False
        
        try:
            profile_url = f"https://www.tiktok.com/@{username}"
            print(f"사용자 프로필로 이동: {profile_url}")
            
            self.page.goto(profile_url, wait_until="networkidle", timeout=60000)
            time.sleep(random.uniform(5, 10))
            
            # CAPTCHA 확인
            if "verify" in self.page.url or "captcha" in self.page.url:
                self.page.screenshot(path=f'debug_{username}_captcha.png')
                print(f"CAPTCHA가 발생했습니다. '{username}' 처리를 건너뜁니다.")
                return False
            
            return True
            
        except Exception as e:
            print(f"프로필 페이지 이동 중 오류: {e}")
            return False
    
    def close(self):
        """브라우저 종료"""
        if self.browser:
            self.browser.close()
        if self.playwright:
            self.playwright.stop()
        print("🔚 브라우저 종료 완료")
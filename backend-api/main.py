import uvicorn
import asyncio
import sys

if __name__ == "__main__":
    # Windows에서 asyncio.ProactorEventLoop를 사용하도록 설정
    # 이는 Playwright와 같은 라이브러리에서 서브프로세스 관련 NotImplementedError를 해결합니다.
    if sys.platform == "win32":
        asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())

    uvicorn.run("app.main:app", host="0.0.0.0", port=8085, reload=True)

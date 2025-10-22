from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from app.api.v1.router import api_router
import time
import asyncio
import sys

if sys.platform == "win32":
    # Playwright와 Windows 호환성을 위해 ProactorEventLoopPolicy 사용
    asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())

app = FastAPI(
    title="Instagram API",
    description="API for Instagram user scraping and messaging",
    version="1.0.0"
)

# CORS 미들웨어 추가
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 요청 로깅 미들웨어
@app.middleware("http")
async def log_requests(request: Request, call_next):
    start_time = time.time()
    print(f"REQUEST: {request.method} {request.url}")
    
    response = await call_next(request)
    
    process_time = time.time() - start_time
    print(f"RESPONSE: {request.method} {request.url} - Status: {response.status_code} - Time: {process_time:.3f}s")
    
    return response

@app.get("/")
def read_root():
    return {"message": "Server is working properly!", "status": "running", "test": "modified"}

# API 라우터 포함
app.include_router(api_router, prefix="/api/v1")

# 모든 라우트 출력 (디버깅용)
@app.on_event("startup")
async def startup_event():
    print("SERVER STARTED!")
    print("Available endpoints:")
    print("  GET  / - Root endpoint")
    print("  POST /api/v1/tiktok/scrape - User scraping1")
    print("  POST /api/v1/tiktok/save_session - Save session2")
    print("  POST /api/v1/tiktok/send_message - Send message3")
    print("  POST /api/v1/tiktok/scrape_video - Scrape user videos")
    print("  GET  /docs - API documentation (Swagger)")
    print("  GET  /redoc - API documentation (ReDoc)")
    print("Server running on http://localhost:8085")
#!/usr/bin/env python3
"""크롤러 모니터링 스크립트 - 크롤러가 멈추거나 오류가 발생하면 알림"""

import time
import subprocess
import pymysql
from pymysql.cursors import DictCursor
from datetime import datetime
import sys

def check_crawler_process(pid):
    """크롤러 프로세스가 실행 중인지 확인"""
    try:
        result = subprocess.run(['ps', '-p', str(pid)], 
                              capture_output=True, text=True)
        return result.returncode == 0
    except:
        return False

def get_db_stats():
    """데이터베이스 통계 조회"""
    db_config = {
        'host': '115.68.208.111',
        'user': 'HanWoori',
        'password': 'Sonnaeun!0513',
        'database': 'hanwoori',
        'charset': 'utf8mb4',
        'cursorclass': DictCursor
    }
    
    try:
        conn = pymysql.connect(**db_config)
        cursor = conn.cursor()
        cursor.execute('SELECT COUNT(*) as cnt FROM funeral_hall')
        hall_count = cursor.fetchone()['cnt']
        cursor.execute('SELECT COUNT(*) as cnt FROM funeral_hall_price')
        price_count = cursor.fetchone()['cnt']
        cursor.close()
        conn.close()
        return hall_count, price_count
    except Exception as e:
        print(f"✗ 데이터베이스 조회 오류: {e}")
        return None, None

def main():
    print("=" * 80)
    print("크롤러 모니터링 시작")
    print("=" * 80)
    print(f"시작 시간: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("")
    
    # 크롤러 프로세스 찾기
    result = subprocess.run(['pgrep', '-f', 'python.*crawler|python.*EOF'], 
                          capture_output=True, text=True)
    
    if result.returncode != 0 or not result.stdout.strip():
        print("✗ 오류: 크롤러 프로세스를 찾을 수 없습니다.")
        print("크롤러가 실행되지 않았거나 이미 종료되었습니다.")
        return
    
    pids = result.stdout.strip().split('\n')
    crawler_pid = pids[0] if pids else None
    
    if not crawler_pid:
        print("✗ 오류: 크롤러 프로세스 ID를 찾을 수 없습니다.")
        return
    
    print(f"크롤러 프로세스 ID: {crawler_pid}")
    
    # 초기 상태
    initial_hall, initial_price = get_db_stats()
    if initial_hall is None:
        print("✗ 오류: 초기 데이터베이스 상태를 확인할 수 없습니다.")
        return
    
    print(f"초기 상태: 장례식장 {initial_hall}개, 가격 정보 {initial_price}개")
    print("")
    print("모니터링 중... (30초마다 확인)")
    print("-" * 80)
    
    # 모니터링 루프
    monitor_count = 0
    last_hall_count = initial_hall
    last_price_count = initial_price
    no_progress_count = 0
    last_check_time = datetime.now()
    
    while True:
        time.sleep(30)  # 30초마다 확인
        monitor_count += 1
        current_time = datetime.now()
        
        # 프로세스 확인
        if not check_crawler_process(crawler_pid):
            print(f"\n{'='*80}")
            print("✗ 크롤러 프로세스가 종료되었습니다!")
            print(f"{'='*80}")
            print(f"종료 시간: {current_time.strftime('%Y-%m-%d %H:%M:%S')}")
            print(f"모니터링 시간: {(current_time - last_check_time).total_seconds():.0f}초")
            
            # 최종 상태
            final_hall, final_price = get_db_stats()
            if final_hall is not None:
                print(f"\n최종 저장된 데이터:")
                print(f"  장례식장: {final_hall}개 (증가: {final_hall - initial_hall}개)")
                print(f"  가격 정보: {final_price}개 (증가: {final_price - initial_price}개)")
            
            print("\n크롤러가 정상 종료되었는지 확인하세요.")
            break
        
        # 데이터베이스 상태 확인
        current_hall, current_price = get_db_stats()
        if current_hall is None:
            print(f"\n⚠ 경고: 데이터베이스 조회 실패 (모니터링 계속)")
            continue
        
        hall_diff = current_hall - last_hall_count
        price_diff = current_price - last_price_count
        
        if hall_diff > 0 or price_diff > 0:
            elapsed = (current_time - last_check_time).total_seconds()
            print(f"[{monitor_count * 30}초] 진행 중... "
                  f"장례식장: {current_hall}개 (+{hall_diff}), "
                  f"가격: {current_price}개 (+{price_diff})")
            last_hall_count = current_hall
            last_price_count = current_price
            no_progress_count = 0
            last_check_time = current_time
        else:
            no_progress_count += 1
            if no_progress_count >= 4:  # 2분 동안 진행 없으면
                print(f"\n⚠ 경고: 2분 동안 데이터 저장이 없습니다.")
                print("  (크롤러가 대기 중이거나 중복 데이터로 인해 skip되고 있을 수 있습니다)")
                no_progress_count = 0

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\n모니터링이 중단되었습니다.")
    except Exception as e:
        print(f"\n✗ 모니터링 오류 발생!")
        print(f"오류 타입: {type(e).__name__}")
        print(f"오류 메시지: {str(e)}")
        import traceback
        traceback.print_exc()

#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
e하늘장사정보서비스 전체 크롤러
메인 페이지와 모든 선택 가능한 옵션 조합 페이지를 크롤링
"""

import sys
import os
import json
import time
import re
import math
from datetime import datetime
from urllib.parse import urljoin, urlparse, parse_qs, urlencode
from itertools import product
from collections import deque

# 사용자 설치 패키지 경로 추가
user_site_packages = os.path.expanduser('~/.local/lib/python3.12/site-packages')
if os.path.exists(user_site_packages) and user_site_packages not in sys.path:
    sys.path.insert(0, user_site_packages)

# 가상환경 경로 추가
venv_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'venv', 'lib', 'python3.12', 'site-packages')
if os.path.exists(venv_path) and venv_path not in sys.path:
    sys.path.insert(0, venv_path)

# 시스템 패키지 경로
system_packages = [
    '/usr/lib/python3/dist-packages',
    '/usr/local/lib/python3.12/dist-packages'
]
for pkg_path in system_packages:
    if os.path.exists(pkg_path) and pkg_path not in sys.path:
        sys.path.append(pkg_path)

# 필수 패키지 임포트
try:
    import requests  # type: ignore
except (ImportError, ModuleNotFoundError) as e:
    print(f"오류: requests 패키지가 설치되지 않았습니다.")
    print(f"설치 방법: sudo apt-get install python3-requests 또는 sudo pip3 install requests")
    sys.exit(1)

try:
    from bs4 import BeautifulSoup  # type: ignore
except (ImportError, ModuleNotFoundError) as e:
    print(f"오류: beautifulsoup4 패키지가 설치되지 않았습니다.")
    print(f"설치 방법: sudo apt-get install python3-bs4 또는 sudo pip3 install beautifulsoup4")
    sys.exit(1)

try:
    import pymysql  # type: ignore
    from pymysql.cursors import DictCursor  # type: ignore
except (ImportError, ModuleNotFoundError) as e:
    print(f"오류: pymysql 패키지가 설치되지 않았습니다.")
    print(f"설치 방법: sudo apt-get install python3-pymysql 또는 sudo pip3 install pymysql")
    sys.exit(1)

# Selenium 관련 패키지 (선택사항)
try:
    from selenium import webdriver  # type: ignore
    from selenium.webdriver.common.by import By  # type: ignore
    from selenium.webdriver.support.ui import WebDriverWait  # type: ignore
    from selenium.webdriver.support import expected_conditions as EC  # type: ignore
    from selenium.webdriver.chrome.options import Options  # type: ignore
    from selenium.webdriver.chrome.service import Service  # type: ignore
    try:
        from webdriver_manager.chrome import ChromeDriverManager  # type: ignore
        SELENIUM_AVAILABLE = True
        WEBDRIVER_MANAGER_AVAILABLE = True
    except ImportError:
        SELENIUM_AVAILABLE = True
        WEBDRIVER_MANAGER_AVAILABLE = False
except ImportError:
    SELENIUM_AVAILABLE = False
    WEBDRIVER_MANAGER_AVAILABLE = False


class EskyCrawler:
    def __init__(self, base_url="https://15774129.go.kr", use_selenium=False):
        self.base_url = base_url
        self.use_selenium = use_selenium and SELENIUM_AVAILABLE
        self.driver = None
        
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language': 'ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding': 'gzip, deflate, br',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1',
        })
        
        # Selenium 드라이버 초기화
        if self.use_selenium:
            self._init_selenium()
        
        # 크롤링된 URL 추적
        self.visited_urls = set()
        self.crawled_data = []
        
        # 데이터베이스 연결 설정
        self.db_config = {
            'host': '115.68.208.111',
            'user': 'HanWoori',
            'password': 'Sonnaeun!0513',
            'database': 'hanwoori',
            'charset': 'utf8mb4',
            'cursorclass': DictCursor
        }
        self.db_conn = None
        
    def _init_selenium(self):
        """Selenium 드라이버 초기화"""
        if not SELENIUM_AVAILABLE:
            print("경고: Selenium이 설치되지 않았습니다.")
            print("설치 방법: pip3 install --user selenium webdriver-manager --break-system-packages")
            self.use_selenium = False
            return
        
        try:
            chrome_options = Options()
            chrome_options.add_argument('--headless=new')  # 새로운 헤드리스 모드
            chrome_options.add_argument('--no-sandbox')
            chrome_options.add_argument('--disable-dev-shm-usage')
            chrome_options.add_argument('--disable-gpu')
            chrome_options.add_argument('--disable-software-rasterizer')
            chrome_options.add_argument('--disable-extensions')
            chrome_options.add_argument('--window-size=1920,1080')
            chrome_options.add_argument('--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
            chrome_options.add_argument('--remote-debugging-port=9222')
            
            # Chrome 드라이버 설정
            if WEBDRIVER_MANAGER_AVAILABLE:
                # webdriver-manager를 사용하여 자동으로 드라이버 다운로드
                service = Service(ChromeDriverManager().install())
                self.driver = webdriver.Chrome(service=service, options=chrome_options)
            else:
                # 시스템에 설치된 Chrome 드라이버 사용
                self.driver = webdriver.Chrome(options=chrome_options)
            
            self.driver.implicitly_wait(10)
            print("Selenium 드라이버 초기화 완료")
        except Exception as e:
            print(f"Selenium 드라이버 초기화 실패: {e}")
            print("Chrome 브라우저와 ChromeDriver가 설치되어 있어야 합니다.")
            print("설치 방법:")
            print("  sudo apt-get update")
            print("  sudo apt-get install -y chromium-browser chromium-chromedriver")
            print("  또는: pip3 install --user selenium webdriver-manager --break-system-packages")
            self.use_selenium = False
            self.driver = None
    
    def close_selenium(self):
        """Selenium 드라이버 종료"""
        if self.driver:
            try:
                self.driver.quit()
                print("Selenium 드라이버 종료")
            except:
                pass
            self.driver = None
    
    def connect_db(self):
        """데이터베이스 연결"""
        try:
            self.db_conn = pymysql.connect(**self.db_config)
            print("데이터베이스 연결 성공")
            return True
        except Exception as e:
            print(f"데이터베이스 연결 실패: {e}")
            return False
    
    def close_db(self):
        """데이터베이스 연결 종료"""
        if self.db_conn:
            self.db_conn.close()
            print("데이터베이스 연결 종료")
    
    def get_page(self, url, params=None, method='GET', data=None, wait_for_element=None):
        """페이지를 가져오는 함수"""
        # Selenium 사용 시
        if self.use_selenium and self.driver:
            try:
                # URL에 파라미터 추가
                if params:
                    from urllib.parse import urlencode
                    if '?' in url:
                        url += '&' + urlencode(params)
                    else:
                        url += '?' + urlencode(params)
                
                self.driver.get(url)
                
                # 특정 요소가 로드될 때까지 대기
                if wait_for_element:
                    try:
                        WebDriverWait(self.driver, 20).until(
                            EC.presence_of_element_located((By.CLASS_NAME, wait_for_element))
                        )
                    except:
                        pass  # 요소를 찾지 못해도 계속 진행
                else:
                    # 기본 대기 시간
                    time.sleep(3)
                
                return self.driver.page_source
            except Exception as e:
                print(f"  Selenium 오류: {e}")
                return None
        
        # 일반 requests 사용
        try:
            # 해시가 있는 경우 URL 그대로 사용 (해시는 서버로 전송되지 않지만 브라우저에서 사용)
            # 실제로는 해시 없이 요청하되, JavaScript가 해시를 처리하도록 함
            request_url = url.split('#')[0]  # 해시 제거하여 실제 요청
            
            if method.upper() == 'POST':
                response = self.session.post(request_url, params=params, data=data, timeout=30)
            else:
                response = self.session.get(request_url, params=params, timeout=30)
            response.raise_for_status()
            response.encoding = 'utf-8'
            html = response.text
            
            # 해시가 있는 경우 JavaScript가 처리하도록 HTML에 해시 정보 추가 시도
            # (실제로는 JavaScript가 실행되지 않지만, 최소한 HTML은 가져옴)
            return html
        except Exception as e:
            print(f"  오류: {e}")
            return None
    
    def get_facility_list_api(self, page=1, page_size=12):
        """API를 통해 장례식장 리스트 가져오기"""
        # 먼저 메인 페이지 방문하여 세션 생성
        try:
            self.session.get(f"{self.base_url}/portal/esky/main/main.do", timeout=10)
        except:
            pass
        
        # 실제 API 엔드포인트: contextPath + "/portal/fnlfac/fac_list.ajax"
        # JavaScript 코드에서 확인: $.pmAjax(contextPath + "/portal/fnlfac/fac_list.ajax", data, ...)
        # contextPath는 빈 문자열이므로 /portal/fnlfac/fac_list.ajax
        # 하지만 실제 경로는 /portal/esky/fnlfac/fac_list.ajax일 수 있음
        api_urls = [
            f"{self.base_url}/portal/fnlfac/fac_list.ajax",  # contextPath가 빈 문자열인 경우
            f"{self.base_url}/portal/esky/fnlfac/fac_list.ajax",  # 전체 경로
        ]
        
        # JavaScript에서 사용하는 파라미터 형식
        data = {
            'pageInqCnt': page_size,
            'curPageNo': page,
            'facilitygroupcd': 'TBC0700001',  # 장례식장 코드
            'sidocd': '',  # 시도 코드 (전체)
            'gungucd': '',  # 시군구 코드 (전체)
            'companyname': '',  # 시설명
            'publiccode': ''  # 공설/사설 (전체)
        }
        
        # 세션 쿠키 설정 (메인 페이지 방문으로 세션 생성)
        headers = {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Referer': f"{self.base_url}/portal/esky/fnlfac/fac_list.do?menuId=M0001000100000000",
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json, text/javascript, */*; q=0.01'
        }
        
        for api_url in api_urls:
            try:
                response = self.session.post(api_url, data=data, headers=headers, timeout=30)
                
                # 404가 아닌 경우에만 처리
                if response.status_code == 404:
                    continue
                
                response.raise_for_status()
                
                # 응답이 JSON인지 확인
                content_type = response.headers.get('Content-Type', '')
                if 'json' in content_type.lower():
                    try:
                        json_data = response.json()
                        # isSuccess 체크
                        if json_data.get('isSuccess', False):
                            return json_data
                        else:
                            print(f"  API 오류: {json_data.get('errorMessage', '알 수 없는 오류')}")
                            continue
                    except Exception as e:
                        print(f"  JSON 파싱 오류: {e}")
                        continue
                
                # HTML 응답인 경우 (오류 페이지가 아닌 경우만)
                if response.text and '존재하지 않는 페이지' not in response.text:
                    return response.text
            except Exception as e:
                if '404' not in str(e):
                    print(f"  API 호출 오류 ({api_url}): {e}")
                continue
        
        return None
    
    def normalize_url(self, url, params=None):
        """URL 정규화 (같은 페이지를 중복 크롤링하지 않도록)"""
        parsed = urlparse(url)
        
        # params가 있으면 쿼리 파라미터에 추가
        query_params = parse_qs(parsed.query)
        if params:
            for key, value in params.items():
                query_params[key] = [str(value)]
        
        # 쿼리 파라미터 정렬
        sorted_params = sorted(query_params.items())
        normalized_query = urlencode(sorted_params, doseq=True)
        normalized_url = f"{parsed.scheme}://{parsed.netloc}{parsed.path}"
        if normalized_query:
            normalized_url += f"?{normalized_query}"
        return normalized_url
    
    def extract_all_links(self, html, current_url):
        """페이지에서 모든 링크 추출"""
        soup = BeautifulSoup(html, 'html.parser')
        links = []
        
        # 모든 <a> 태그에서 링크 추출
        for link in soup.find_all('a', href=True):
            href = link.get('href', '')
            if href:
                # 상대 경로를 절대 경로로 변환
                absolute_url = urljoin(current_url, str(href))
                # 같은 도메인인지 확인
                if self.base_url in absolute_url:
                    links.append({
                        'url': absolute_url,
                        'text': link.get_text(strip=True),
                        'type': 'link'
                    })
        
        # form의 action URL도 추출
        for form in soup.find_all('form', action=True):
            action = form.get('action', '')
            if action:
                absolute_url = urljoin(current_url, str(action))
                if self.base_url in absolute_url:
                    links.append({
                        'url': absolute_url,
                        'text': form.get('name', '') or 'form',
                        'type': 'form'
                    })
        
        return links
    
    def find_menu_link(self, html, current_url, menu_text):
        """메인 페이지에서 특정 메뉴 링크 찾기"""
        soup = BeautifulSoup(html, 'html.parser')
        
        # 방법 1: 링크 텍스트로 직접 찾기
        for link in soup.find_all('a', href=True):
            link_text = link.get_text(strip=True)
            if menu_text in link_text or link_text in menu_text:
                href = link.get('href', '')
                if href:
                    absolute_url = urljoin(current_url, str(href))
                    if self.base_url in absolute_url:
                        print(f"메뉴 링크 발견: '{link_text}' -> {absolute_url}")
                        return absolute_url
        
        # 방법 2: title 속성으로 찾기
        for link in soup.find_all('a', href=True):
            title = link.get('title', '')
            if menu_text in title or title in menu_text:
                href = link.get('href', '')
                if href:
                    absolute_url = urljoin(current_url, str(href))
                    if self.base_url in absolute_url:
                        print(f"메뉴 링크 발견 (title): '{title}' -> {absolute_url}")
                        return absolute_url
        
        # 방법 3: href에 특정 패턴이 있는지 확인 (fnlfac, fac_list 등)
        if '장례용품가격' in menu_text or '장사시설/장례용품가격' in menu_text:
            for link in soup.find_all('a', href=True):
                href = link.get('href', '')
                if href and ('fnlfac' in str(href) or 'fac_list' in str(href) or 'fac_price' in str(href)):
                    absolute_url = urljoin(current_url, str(href))
                    if self.base_url in absolute_url:
                        link_text = link.get_text(strip=True)
                        print(f"메뉴 링크 발견 (패턴): '{link_text}' -> {absolute_url}")
                        return absolute_url
        
        return None
    
    def navigate_to_menu(self, main_url, menu_text):
        """메인 페이지에서 특정 메뉴로 이동"""
        print(f"\n메인 페이지에서 '{menu_text}' 메뉴 찾는 중...")
        print(f"메인 페이지 URL: {main_url}")
        
        html = self.get_page(main_url)
        if not html:
            print("메인 페이지를 가져올 수 없습니다.")
            return None
        
        menu_url = self.find_menu_link(html, main_url, menu_text)
        if menu_url:
            print(f"✓ 메뉴 링크 찾음: {menu_url}")
            return menu_url
        else:
            print(f"✗ '{menu_text}' 메뉴 링크를 찾을 수 없습니다.")
            # 기본 URL 시도
            if '장례용품가격' in menu_text or '장사시설/장례용품가격' in menu_text:
                default_url = f"{self.base_url}/portal/esky/fnlfac/fac_list.do?menuId=M0001000100000000"
                print(f"기본 URL 사용: {default_url}")
                return default_url
            return None
    
    def extract_facilities_from_api_data(self, api_data, current_url):
        """API 응답 데이터에서 장례식장 정보 추출"""
        facilities = []
        
        # API 응답 구조에 따라 데이터 추출
        if isinstance(api_data, dict):
            # 일반적인 API 응답 구조
            data_keys = ['data', 'list', 'result', 'items', 'facilityList', 'facList', 'facility', 'facilities']
            facility_list = None
            
            for key in data_keys:
                if key in api_data:
                    value = api_data[key]
                    if isinstance(value, list):
                        facility_list = value
                        break
                    elif isinstance(value, dict):
                        # 중첩된 구조일 수 있음
                        for sub_key in ['list', 'items', 'data']:
                            if sub_key in value and isinstance(value[sub_key], list):
                                facility_list = value[sub_key]
                                break
                        if facility_list:
                            break
            
            # 키를 찾지 못한 경우 모든 리스트 값 찾기
            if not facility_list:
                for key, value in api_data.items():
                    if isinstance(value, list) and len(value) > 0:
                        # 리스트의 첫 번째 항목이 딕셔너리인지 확인
                        if isinstance(value[0], dict):
                            # facId나 facNm 같은 키가 있는지 확인
                            first_item = value[0]
                            if 'facId' in first_item or 'facNm' in first_item or 'fac_id' in first_item or 'fac_name' in first_item:
                                facility_list = value
                                break
            
            if facility_list:
                print(f"  API에서 {len(facility_list)}개 항목 발견")
                for item in facility_list:
                    if isinstance(item, dict):
                        # API 응답 구조: facilitycd가 facId, companyname이 시설명
                        fac_id = item.get('facilitycd') or item.get('facId') or item.get('fac_id') or item.get('id') or item.get('facilityId')
                        name = item.get('companyname') or item.get('facNm') or item.get('fac_name') or item.get('name') or item.get('facilityName')
                        
                        if fac_id:
                            menu_id = 'M0001000100000000'
                            info_url = f"{self.base_url}/portal/esky/fnlfac/fac_view.do?menuId={menu_id}&facId={fac_id}"
                            price_url = f"{self.base_url}/portal/esky/fnlfac/fac_price.do?menuId={menu_id}&facId={fac_id}"
                            
                            facilities.append({
                                'name': name or f"장례식장_{fac_id}",
                                'info_url': info_url,
                                'price_url': price_url,
                                'fac_id': fac_id
                            })
        
        return facilities
    
    def extract_facility_items(self, html, current_url):
        """리스트 페이지에서 장례식장 항목과 시설정보/가격정보 버튼 추출"""
        soup = BeautifulSoup(html, 'html.parser')
        facilities = []
        processed_fac_ids = set()  # 중복 방지
        
        # 방법 1: 각 장례식장 항목을 찾기 (리스트 아이템, 카드, 테이블 행 등)
        # 일반적으로 각 장례식장은 하나의 컨테이너(div, li, tr 등)에 있음
        
        # 리스트 아이템 찾기 (ul > li 구조)
        list_items = soup.find_all(['li', 'div'], class_=re.compile(r'item|list|card|facility', re.I))
        if not list_items:
            # 테이블 행 찾기
            list_items = soup.find_all('tr')
        
        for item in list_items:
            facility_name = ''
            facility_info_url = None
            facility_price_url = None
            fac_id = None
            
            # 장례식장 이름 찾기 (일반적으로 링크나 제목)
            name_elem = item.find(['a', 'h3', 'h4', 'h5', 'div'], class_=re.compile(r'name|title|facility', re.I))
            if name_elem:
                facility_name = name_elem.get_text(strip=True)
                # 이름이 있는 링크에서 facId 추출 시도
                name_link = name_elem.find('a', href=True) if name_elem.name != 'a' else name_elem
                if name_link and name_link.get('href'):
                    href_str = str(name_link.get('href'))
                    if 'facId' in href_str:
                        parsed = urlparse(urljoin(current_url, href_str))
                        query_params = parse_qs(parsed.query)
                        fac_id = query_params.get('facId', [None])[0]
            
            # 이름이 없으면 전체 텍스트에서 첫 번째 의미있는 텍스트 찾기
            if not facility_name:
                item_text = item.get_text(strip=True)
                # 첫 번째 줄이나 링크 텍스트 사용
                first_link = item.find('a', href=True)
                if first_link:
                    facility_name = first_link.get_text(strip=True)
                elif item_text:
                    # 첫 50자만 사용
                    facility_name = item_text[:50].split('\n')[0].strip()
            
            # 시설정보 버튼/링크 찾기
            # "시설정보" 텍스트를 가진 버튼이나 링크 찾기
            info_buttons = []
            for elem in item.find_all(['a', 'button']):
                text = elem.get_text(strip=True)
                if re.search(r'시설정보|시설', text, re.I):
                    info_buttons.append(elem)
            if not info_buttons:
                # href에 fac_view가 있는 링크 찾기
                for link in item.find_all('a', href=True):
                    href = str(link.get('href', ''))
                    if 'fac_view' in href:
                        info_buttons.append(link)
            
            for btn in info_buttons:
                href = btn.get('href', '')
                if href:
                    href_str = str(href)
                    absolute_url = urljoin(current_url, href_str)
                    facility_info_url = absolute_url
                    # facId 추출
                    if 'facId' in href_str:
                        parsed = urlparse(absolute_url)
                        query_params = parse_qs(parsed.query)
                        fac_id = query_params.get('facId', [None])[0]
                    break
            
            # 가격정보 버튼/링크 찾기
            # "가격정보" 텍스트를 가진 버튼이나 링크 찾기
            price_buttons = []
            for elem in item.find_all(['a', 'button']):
                text = elem.get_text(strip=True)
                if re.search(r'가격정보|가격', text, re.I):
                    price_buttons.append(elem)
            if not price_buttons:
                # href에 price가 있는 링크 찾기
                for link in item.find_all('a', href=True):
                    href = str(link.get('href', '')).lower()
                    if 'price' in href or 'fnlprc' in href:
                        price_buttons.append(link)
            
            for btn in price_buttons:
                href = btn.get('href', '')
                if href:
                    href_str = str(href)
                    absolute_url = urljoin(current_url, href_str)
                    facility_price_url = absolute_url
                    # facId 추출
                    if 'facId' in href_str and not fac_id:
                        parsed = urlparse(absolute_url)
                        query_params = parse_qs(parsed.query)
                        fac_id = query_params.get('facId', [None])[0]
                    break
            
            # facId가 있지만 URL이 없는 경우 URL 생성
            if fac_id and not facility_info_url:
                menu_id = 'M0001000100000000'
                facility_info_url = f"{self.base_url}/portal/esky/fnlfac/fac_view.do?menuId={menu_id}&facId={fac_id}"
            if fac_id and not facility_price_url:
                menu_id = 'M0001000100000000'
                facility_price_url = f"{self.base_url}/portal/esky/fnlfac/fac_price.do?menuId={menu_id}&facId={fac_id}"
            
            # 장례식장 정보가 있으면 추가
            if facility_name and (facility_info_url or facility_price_url or fac_id):
                # 중복 체크
                if fac_id:
                    if fac_id in processed_fac_ids:
                        continue
                    processed_fac_ids.add(fac_id)
                elif facility_name in [f['name'] for f in facilities]:
                    continue
                
                facilities.append({
                    'name': facility_name,
                    'info_url': facility_info_url,
                    'price_url': facility_price_url,
                    'fac_id': fac_id
                })
        
        # 방법 2: 테이블에서 추출 (기존 로직)
        if not facilities:
            tables = soup.find_all('table')
            for table in tables:
                rows = table.find_all('tr')
                for row in rows:
                    cells = row.find_all(['td', 'th'])
                    if len(cells) < 2:
                        continue
                    
                    facility_name = ''
                    facility_info_url = None
                    facility_price_url = None
                    fac_id = None
                    
                    for cell in cells:
                        cell_text = cell.get_text(strip=True)
                        
                        if not facility_name and cell_text and len(cell_text) > 2:
                            link = cell.find('a', href=True)
                            if link:
                                facility_name = link.get_text(strip=True)
                            else:
                                facility_name = cell_text
                        
                        links = cell.find_all('a', href=True)
                        for link in links:
                            href = link.get('href', '')
                            if not href:
                                continue
                            href_str = str(href)
                            link_text = link.get_text(strip=True)
                            absolute_url = urljoin(current_url, href_str)
                            
                            if 'facId' in href_str:
                                parsed = urlparse(absolute_url)
                                query_params = parse_qs(parsed.query)
                                fac_id = query_params.get('facId', [None])[0]
                            
                            if 'fac_view' in href_str or '시설정보' in link_text:
                                facility_info_url = absolute_url
                            elif 'price' in href_str.lower() or '가격정보' in link_text or 'fnlprc' in href_str.lower():
                                facility_price_url = absolute_url
                    
                    if facility_name and (facility_info_url or fac_id):
                        if fac_id and fac_id not in processed_fac_ids:
                            processed_fac_ids.add(fac_id)
                            facilities.append({
                                'name': facility_name,
                                'info_url': facility_info_url,
                                'price_url': facility_price_url,
                                'fac_id': fac_id
                            })
        
        # 방법 3: 모든 링크에서 fac_view 찾기
        if not facilities:
            print("  모든 링크에서 fac_view 검색 중...")
            for link in soup.find_all('a', href=True):
                href = link.get('href', '')
                if not href:
                    continue
                href_str = str(href)
                
                if 'fac_view' in href_str or 'facId' in href_str:
                    absolute_url = urljoin(current_url, href_str)
                    parsed = urlparse(absolute_url)
                    query_params = parse_qs(parsed.query)
                    fac_id = query_params.get('facId', [None])[0]
                    
                    if fac_id and fac_id not in processed_fac_ids:
                        link_text = link.get_text(strip=True)
                        processed_fac_ids.add(fac_id)
                        menu_id = 'M0001000100000000'
                        info_url = absolute_url if 'fac_view' in href_str else f"{self.base_url}/portal/esky/fnlfac/fac_view.do?menuId={menu_id}&facId={fac_id}"
                        price_url = f"{self.base_url}/portal/esky/fnlfac/fac_price.do?menuId={menu_id}&facId={fac_id}"
                        
                        facilities.append({
                            'name': link_text or f"장례식장_{fac_id}",
                            'info_url': info_url,
                            'price_url': price_url,
                            'fac_id': fac_id
                        })
        
        return facilities
    
    def extract_pagination_links(self, html, current_url):
        """페이지네이션 링크 추출"""
        soup = BeautifulSoup(html, 'html.parser')
        page_links = []
        
        # 페이지네이션 영역 찾기 (일반적으로 class에 'paging', 'pagination', 'page' 등이 포함)
        pagination = soup.find(class_=re.compile(r'paging|pagination|page', re.I))
        if not pagination:
            # 페이지네이션 영역을 찾지 못한 경우 모든 링크에서 페이지 번호 찾기
            pagination = soup
        
        for link in pagination.find_all('a', href=True):
            href = link.get('href', '')
            if not href:
                continue
            href_str = str(href)
            link_text = link.get_text(strip=True)
            
            # 페이지 번호가 포함된 링크 찾기
            if href_str and ('page' in href_str.lower() or link_text.isdigit() or '다음' in link_text or '이전' in link_text):
                absolute_url = urljoin(current_url, href_str)
                # 같은 페이지 리스트인지 확인
                if 'fac_list' in absolute_url:
                    page_links.append(absolute_url)
        
        return page_links
    
    def extract_all_options(self, html):
        """페이지에서 모든 select 옵션 추출"""
        soup = BeautifulSoup(html, 'html.parser')
        options = {}
        
        selects = soup.find_all('select')
        for select in selects:
            select_name = select.get('name') or select.get('id', '')
            if not select_name:
                continue
            
            option_values = []
            for option in select.find_all('option'):
                value = option.get('value', '')
                text = option.get_text(strip=True)
                # 빈 값이나 "선택" 텍스트 제외
                if value and value != '' and text and '선택' not in text:
                    option_values.append({
                        'value': value,
                        'text': text
                    })
            
            if option_values:
                options[select_name] = option_values
        
        return options
    
    def get_facility_detail_api(self, fac_id, sanbundiv='N'):
        """API를 통해 장례식장 상세 정보 가져오기
        sanbundiv='N'을 사용해야 실제 가격 정보를 가져올 수 있습니다.
        """
        api_url = f"{self.base_url}/portal/fnlfac/price_info.ajax"
        
        data = {
            'facilitycd': fac_id,
            'sanbundiv': sanbundiv  # 'N'을 사용해야 실제 가격 데이터를 가져올 수 있음
        }
        
        headers = {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Referer': f"{self.base_url}/portal/esky/fnlfac/fac_view.do?menuId=M0001000100000000&facId={fac_id}",
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json, text/javascript, */*; q=0.01'
        }
        
        try:
            response = self.session.post(api_url, data=data, headers=headers, timeout=30)
            response.raise_for_status()
            
            content_type = response.headers.get('Content-Type', '')
            if 'json' in content_type.lower():
                try:
                    json_data = response.json()
                    return json_data
                except:
                    pass
        except Exception as e:
            print(f"  API 호출 오류: {e}")
        
        return None
    
    def extract_price_from_html(self, html, url):
        """HTML 소스에서 직접 가격 정보 추출 (Selenium 없이)"""
        try:
            from bs4 import BeautifulSoup
            soup = BeautifulSoup(html, 'html.parser')
            
            price_data = {
                'hallRent': [],
                'commission': [],
                'funeralItem': []
            }
            
            # 모든 테이블 찾기
            all_tables = soup.find_all('table')
            
            for table in all_tables:
                # 테이블의 텍스트로 카테고리 판단
                table_text = table.get_text()
                
                rows = table.find_all('tr')
                for row in rows:
                    cells = row.find_all(['td', 'th'])
                    if len(cells) < 3:  # 최소 3개 셀 필요
                        continue
                    
                    cell_texts = [cell.get_text(strip=True) for cell in cells]
                    
                    # 헤더 행 스킵 (th 태그가 있거나, 헤더 텍스트 포함)
                    is_header = False
                    if row.find('th'):
                        is_header = True
                    elif any(text in ['품종', '품명', '요금', '일수', '사용료내역', '서비스내용', '규격', '재질', '원산지', '선택', '수량'] for text in cell_texts[:4]):
                        is_header = True
                    elif len([t for t in cell_texts[:4] if t and t != ""]) <= 1:  # 대부분 빈 셀
                        is_header = True
                    
                    if is_header:
                        continue
                    
                    # 실제 데이터 행인지 확인
                    # tier2Nm이 있어야 하고, 헤더 텍스트가 아니어야 함
                    tier2_nm = cell_texts[0] if len(cell_texts) > 0 else ""
                    
                    # tier2Nm이 헤더 텍스트인지 확인
                    header_keywords = ['품종', '품명', '요금', '일수', '시설사용료', '서비스 항목', '장사용품', 
                                     '사용료내역', '서비스내용', '규격', '재질', '원산지', '선택', '수량',
                                     '구분', '항목', '패키지', '정가', '판매가', '장례기간', '식사 포함여부']
                    
                    if not tier2_nm or tier2_nm in header_keywords or tier2_nm == "-":
                        continue
                    
                    # 가격 정보가 있는 행인지 확인 (숫자 포함)
                    has_price = False
                    price_value = ""
                    for text in cell_texts:
                        # 숫자 패턴 찾기 (예: 500,000, 500000, 1회 등)
                        price_match = re.search(r'(\d{1,3}(?:[,\.]\d{3})*(?:,\d+)?)', text)
                        if price_match:
                            has_price = True
                            price_value = price_match.group(1)
                            break
                    
                    # 실제 데이터가 있는 행만 추출
                    if tier2_nm and tier2_nm not in header_keywords:
                        item = cell_texts[1] if len(cell_texts) > 1 else ""
                        content = cell_texts[2] if len(cell_texts) > 2 else ""
                        days = cell_texts[3] if len(cell_texts) > 3 else ""
                        
                        # 요금이 다른 컬럼에 있을 수 있음
                        if len(cell_texts) > 4:
                            potential_price = cell_texts[4]
                            if re.search(r'\d+', potential_price):
                                price_value = potential_price
                        
                        # 카테고리 판단
                        if '시설' in table_text or '임대' in table_text or 'hallRent' in str(table) or '시설사용료' in table_text:
                            price_data['hallRent'].append({
                                'tier2Nm': tier2_nm,
                                'item': item if item and item != "-" else "",
                                'rentcontent': content if content and content != "-" else "",
                                'facilityamt': price_value if price_value else "",
                                'facilityamtm': days if days else ""
                            })
                        elif '서비스' in table_text or '식사' in table_text or 'commission' in str(table) or '서비스 항목' in table_text:
                            price_data['commission'].append({
                                'tier2Nm': tier2_nm,
                                'item': item if item and item != "-" else "",
                                'servcontent': content if content and content != "-" else "",
                                'facilityamt': price_value if price_value else "",
                                'facilityamtm': days if days else ""
                            })
                        elif '장사' in table_text or '용품' in table_text or 'funeralItem' in str(table) or '장사용품' in table_text:
                            price_data['funeralItem'].append({
                                'tier2Nm': tier2_nm,
                                'commodity': item if item and item != "-" else "",
                                'etcinfo': content if content and content != "-" else "",
                                'commamt': price_value if price_value else "",
                                'commamtm': days if days else ""
                            })
            
            # JavaScript 변수에서 가격 정보 찾기
            scripts = soup.find_all('script')
            for script in scripts:
                script_text = script.string
                if script_text:
                    # JavaScript 객체에서 가격 정보 추출 시도
                    # 예: var priceData = {...}
                    price_patterns = [
                        r'hallRent\s*[:=]\s*\[(.*?)\]',
                        r'facilityamt\s*[:=]\s*["\']?(\d+)',
                        r'item\s*[:=]\s*["\']([^"\']+)["\']',
                    ]
                    
                    for pattern in price_patterns:
                        matches = re.finditer(pattern, script_text, re.DOTALL)
                        for match in matches:
                            # 간단한 파싱 (복잡한 경우는 생략)
                            pass
            
            if price_data['hallRent'] or price_data['commission'] or price_data['funeralItem']:
                print(f"    HTML에서 가격 정보 추출 성공: hallRent={len(price_data['hallRent'])}, commission={len(price_data['commission'])}, funeralItem={len(price_data['funeralItem'])}")
                return price_data
            
        except Exception as e:
            print(f"  HTML 가격 정보 추출 오류: {e}")
        
        return None
    
    def extract_price_from_selenium(self, url):
        """Selenium을 사용하여 실제 렌더링된 페이지에서 가격 정보 추출"""
        if not self.use_selenium or not self.driver:
            return None
        
        try:
            print(f"  Selenium으로 가격 정보 추출 중: {url}")
            self.driver.get(url)
            
            # 페이지 로딩 대기
            import time
            time.sleep(3)
            
            # 가격 정보가 있는 테이블 찾기
            price_data = {
                'hallRent': [],
                'commission': [],
                'funeralItem': []
            }
            
            # JavaScript로 동적으로 생성된 테이블 찾기
            try:
                # 시설사용료 테이블 찾기
                hallrent_tables = self.driver.find_elements("css selector", "#table_hallRent table, .price_table, table[id*='hallRent']")
                for table in hallrent_tables:
                    rows = table.find_elements("css selector", "tbody tr, tr")
                    for row in rows:
                        cells = row.find_elements("css selector", "td")
                        if len(cells) >= 4:
                            tier2_nm = cells[0].text.strip() if len(cells) > 0 else ""
                            item = cells[1].text.strip() if len(cells) > 1 else ""
                            rentcontent = cells[2].text.strip() if len(cells) > 2 else ""
                            facilityamtm = cells[3].text.strip() if len(cells) > 3 else ""
                            # 요금은 다른 컬럼에 있을 수 있음
                            facilityamt = ""
                            if len(cells) > 4:
                                facilityamt = cells[4].text.strip()
                            
                            if tier2_nm and tier2_nm != "-":
                                price_data['hallRent'].append({
                                    'tier2Nm': tier2_nm,
                                    'item': item if item and item != "-" else "",
                                    'rentcontent': rentcontent if rentcontent and rentcontent != "-" else "",
                                    'facilityamt': facilityamt if facilityamt else 0,
                                    'facilityamtm': facilityamtm if facilityamtm else ""
                                })
            except Exception as e:
                print(f"    hallRent 테이블 추출 오류: {e}")
            
            # 서비스 항목 테이블 찾기
            try:
                commission_tables = self.driver.find_elements("css selector", "#table_commission table, table[id*='commission']")
                for table in commission_tables:
                    rows = table.find_elements("css selector", "tbody tr, tr")
                    for row in rows:
                        cells = row.find_elements("css selector", "td")
                        if len(cells) >= 4:
                            tier2_nm = cells[0].text.strip() if len(cells) > 0 else ""
                            item = cells[1].text.strip() if len(cells) > 1 else ""
                            servcontent = cells[2].text.strip() if len(cells) > 2 else ""
                            facilityamtm = cells[3].text.strip() if len(cells) > 3 else ""
                            facilityamt = ""
                            if len(cells) > 4:
                                facilityamt = cells[4].text.strip()
                            
                            if tier2_nm and tier2_nm != "-":
                                price_data['commission'].append({
                                    'tier2Nm': tier2_nm,
                                    'item': item if item and item != "-" else "",
                                    'servcontent': servcontent if servcontent and servcontent != "-" else "",
                                    'facilityamt': facilityamt if facilityamt else 0,
                                    'facilityamtm': facilityamtm if facilityamtm else ""
                                })
            except Exception as e:
                print(f"    commission 테이블 추출 오류: {e}")
            
            # 장사용품 테이블 찾기
            try:
                funeralitem_tables = self.driver.find_elements("css selector", "#table_funeralItem table, table[id*='funeralItem']")
                for table in funeralitem_tables:
                    rows = table.find_elements("css selector", "tbody tr, tr")
                    for row in rows:
                        cells = row.find_elements("css selector", "td")
                        if len(cells) >= 4:
                            tier2_nm = cells[0].text.strip() if len(cells) > 0 else ""
                            commodity = cells[1].text.strip() if len(cells) > 1 else ""
                            etcinfo = cells[2].text.strip() if len(cells) > 2 else ""
                            commamtm = cells[3].text.strip() if len(cells) > 3 else ""
                            commamt = ""
                            if len(cells) > 4:
                                commamt = cells[4].text.strip()
                            
                            if tier2_nm and tier2_nm != "-":
                                price_data['funeralItem'].append({
                                    'tier2Nm': tier2_nm,
                                    'commodity': commodity if commodity and commodity != "-" else "",
                                    'etcinfo': etcinfo if etcinfo and etcinfo != "-" else "",
                                    'commamt': commamt if commamt else 0,
                                    'commamtm': commamtm if commamtm else ""
                                })
            except Exception as e:
                print(f"    funeralItem 테이블 추출 오류: {e}")
            
            # 가격 정보가 하나라도 있으면 반환
            if price_data['hallRent'] or price_data['commission'] or price_data['funeralItem']:
                print(f"    Selenium에서 가격 정보 추출 성공: hallRent={len(price_data['hallRent'])}, commission={len(price_data['commission'])}, funeralItem={len(price_data['funeralItem'])}")
                return price_data
            
            # 테이블을 찾지 못한 경우, 페이지 소스에서 직접 추출 시도
            page_source = self.driver.page_source
            from bs4 import BeautifulSoup
            soup = BeautifulSoup(page_source, 'html.parser')
            
            # 모든 테이블에서 가격 정보 찾기
            all_tables = soup.find_all('table')
            for table in all_tables:
                rows = table.find_all('tr')
                for row in rows:
                    cells = row.find_all(['td', 'th'])
                    if len(cells) >= 4:
                        cell_texts = [cell.get_text(strip=True) for cell in cells]
                        # 가격 정보 패턴 확인 (숫자 포함)
                        has_price = any(re.search(r'\d+[,\.]?\d*', text) for text in cell_texts)
                        if has_price and any('품종' in text or '품명' in text or '요금' in text for text in cell_texts):
                            # 가격 정보로 보이는 행
                            if len(cell_texts) >= 4:
                                tier2_nm = cell_texts[0] if cell_texts[0] else ""
                                item = cell_texts[1] if len(cell_texts) > 1 else ""
                                content = cell_texts[2] if len(cell_texts) > 2 else ""
                                price = cell_texts[3] if len(cell_texts) > 3 else ""
                                days = cell_texts[4] if len(cell_texts) > 4 else ""
                                
                                if tier2_nm and tier2_nm != "-" and tier2_nm != "품종":
                                    # 어떤 카테고리인지 판단
                                    if '시설' in str(table) or '임대' in str(table):
                                        price_data['hallRent'].append({
                                            'tier2Nm': tier2_nm,
                                            'item': item,
                                            'rentcontent': content,
                                            'facilityamt': price,
                                            'facilityamtm': days
                                        })
                                    elif '서비스' in str(table) or '식사' in str(table):
                                        price_data['commission'].append({
                                            'tier2Nm': tier2_nm,
                                            'item': item,
                                            'servcontent': content,
                                            'facilityamt': price,
                                            'facilityamtm': days
                                        })
                                    else:
                                        price_data['funeralItem'].append({
                                            'tier2Nm': tier2_nm,
                                            'commodity': item,
                                            'etcinfo': content,
                                            'commamt': price,
                                            'commamtm': days
                                        })
            
            if price_data['hallRent'] or price_data['commission'] or price_data['funeralItem']:
                print(f"    페이지 소스에서 가격 정보 추출 성공: hallRent={len(price_data['hallRent'])}, commission={len(price_data['commission'])}, funeralItem={len(price_data['funeralItem'])}")
                return price_data
            
        except Exception as e:
            print(f"  Selenium 가격 정보 추출 오류: {e}")
            import traceback
            traceback.print_exc()
        
        return None
    
    def extract_page_data(self, html, url):
        """페이지에서 장례식장 정보와 가격정보만 추출 (메뉴, 헤더, 푸터 제외)"""
        # fac_view 페이지인 경우 API로 실제 정보 가져오기
        if 'fac_view' in url:
            # URL에서 facId 추출
            from urllib.parse import urlparse, parse_qs
            parsed = urlparse(url)
            query_params = parse_qs(parsed.query)
            fac_id = query_params.get('facId', [None])[0]
            
            if fac_id:
                # API로 실제 장례식장 정보 가져오기
                api_data = self.get_facility_detail_api(fac_id)
                
                # API 데이터에 실제 가격 정보가 없는 경우 다른 방법으로 추출 시도
                extracted_price_data = None
                if api_data:
                    # API 데이터에 실제 가격 정보가 있는지 확인
                    has_real_price = False
                    for item in api_data.get('hallRent', []):
                        if (item.get('item') and item.get('item') != '-' and 
                            item.get('rentcontent') and item.get('rentcontent') != '-' and
                            (item.get('facilityamt', 0) > 0 or (item.get('facilityamtm') and item.get('facilityamtm') != ''))):
                            has_real_price = True
                            break
                    
                    if not has_real_price:
                        # 방법 1: HTML 소스에서 직접 추출
                        try:
                            page_response = self.session.get(url, timeout=30)
                            if page_response.status_code == 200:
                                extracted_price_data = self.extract_price_from_html(page_response.text, url)
                        except Exception as e:
                            print(f"    HTML 추출 시도 중 오류: {e}")
                        
                        # 방법 2: Selenium으로 실제 렌더링된 페이지에서 가격 정보 추출 (HTML 추출 실패 시)
                        if not extracted_price_data and self.use_selenium and self.driver:
                            extracted_price_data = self.extract_price_from_selenium(url)
                        
                        if extracted_price_data:
                            # 추출한 데이터를 API 데이터에 병합
                            if extracted_price_data.get('hallRent'):
                                # API 데이터의 tier2Nm과 매칭하여 업데이트
                                for api_item in api_data.get('hallRent', []):
                                    tier2_nm = api_item.get('tier2Nm', '')
                                    for ext_item in extracted_price_data['hallRent']:
                                        if ext_item.get('tier2Nm') == tier2_nm:
                                            # 추출한 데이터로 업데이트
                                            api_item['item'] = ext_item.get('item', api_item.get('item', '-'))
                                            api_item['rentcontent'] = ext_item.get('rentcontent', api_item.get('rentcontent', '-'))
                                            # 숫자로 변환 시도
                                            facilityamt = ext_item.get('facilityamt', '')
                                            if facilityamt:
                                                try:
                                                    # 쉼표 제거 후 숫자로 변환
                                                    facilityamt_clean = str(facilityamt).replace(',', '').replace('.', '')
                                                    api_item['facilityamt'] = int(facilityamt_clean) if facilityamt_clean.isdigit() else facilityamt
                                                except:
                                                    api_item['facilityamt'] = facilityamt
                                            else:
                                                api_item['facilityamt'] = api_item.get('facilityamt', 0)
                                            api_item['facilityamtm'] = ext_item.get('facilityamtm', api_item.get('facilityamtm', ''))
                                            break
                            
                            if extracted_price_data.get('commission'):
                                for api_item in api_data.get('commission', []):
                                    tier2_nm = api_item.get('tier2Nm', '')
                                    for ext_item in extracted_price_data['commission']:
                                        if ext_item.get('tier2Nm') == tier2_nm:
                                            api_item['item'] = ext_item.get('item', api_item.get('item', '-'))
                                            api_item['servcontent'] = ext_item.get('servcontent', api_item.get('servcontent', '-'))
                                            facilityamt = ext_item.get('facilityamt', '')
                                            if facilityamt:
                                                try:
                                                    facilityamt_clean = str(facilityamt).replace(',', '').replace('.', '')
                                                    api_item['facilityamt'] = int(facilityamt_clean) if facilityamt_clean.isdigit() else facilityamt
                                                except:
                                                    api_item['facilityamt'] = facilityamt
                                            else:
                                                api_item['facilityamt'] = api_item.get('facilityamt', 0)
                                            api_item['facilityamtm'] = ext_item.get('facilityamtm', api_item.get('facilityamtm', ''))
                                            break
                            
                            if extracted_price_data.get('funeralItem'):
                                for api_item in api_data.get('funeralItem', []):
                                    tier2_nm = api_item.get('tier2Nm', '')
                                    for ext_item in extracted_price_data['funeralItem']:
                                        if ext_item.get('tier2Nm') == tier2_nm:
                                            api_item['commodity'] = ext_item.get('commodity', api_item.get('commodity', '-'))
                                            api_item['etcinfo'] = ext_item.get('etcinfo', api_item.get('etcinfo', '-'))
                                            commamt = ext_item.get('commamt', '')
                                            if commamt:
                                                try:
                                                    commamt_clean = str(commamt).replace(',', '').replace('.', '')
                                                    api_item['commamt'] = int(commamt_clean) if commamt_clean.isdigit() else commamt
                                                except:
                                                    api_item['commamt'] = commamt
                                            else:
                                                api_item['commamt'] = api_item.get('commamt', 0)
                                            api_item['commamtm'] = ext_item.get('commamtm', api_item.get('commamtm', ''))
                                            break
                
                if api_data and isinstance(api_data, dict):
                    detail = api_data.get('detail', {})
                    if detail:
                        # 실제 장례식장 정보만 추출
                        facility_info = []
                        
                        # 기본 정보
                        if detail.get('companyname'):
                            facility_info.append(f"시설명: {detail.get('companyname')}")
                        if detail.get('fulladdress'):
                            facility_info.append(f"주소: {detail.get('fulladdress')}")
                        if detail.get('telephone'):
                            facility_info.append(f"전화번호: {detail.get('telephone')}")
                        if detail.get('representativename'):
                            facility_info.append(f"대표자명: {detail.get('representativename')}")
                        if detail.get('companyno'):
                            facility_info.append(f"사업자등록번호: {detail.get('companyno')}")
                        if detail.get('homepage'):
                            facility_info.append(f"홈페이지: {detail.get('homepage')}")
                        if detail.get('businessdateS'):
                            facility_info.append(f"개업일자: {detail.get('businessdateS')}")
                        if detail.get('manageclassdiv'):
                            facility_info.append(f"운영형태: {detail.get('manageclassdiv')}")
                        if detail.get('funeraltypecd'):
                            facility_info.append(f"장례방법: {detail.get('funeraltypecd')}")
                        if detail.get('mortuaycnt'):
                            facility_info.append(f"빈소 수: {detail.get('mortuaycnt')}개")
                        if detail.get('charnelabilitycnt'):
                            facility_info.append(f"안치 능력: {detail.get('charnelabilitycnt')}개")
                        if detail.get('parkcnt'):
                            facility_info.append(f"주차 대수: {detail.get('parkcnt')}대")
                        # 대중교통, 자가교통 정보는 저장하지 않음 (사용자 요청)
                        # if detail.get('traffpublic'):
                        #     facility_info.append(f"대중교통: {detail.get('traffpublic')}")
                        # if detail.get('traffowner'):
                        #     facility_info.append(f"자가교통: {detail.get('traffowner')}")
                        if detail.get('etcinfw'):
                            facility_info.append(f"기타정보: {detail.get('etcinfw')}")
                        
                        # 시설 정보
                        facility_info.append("\n[시설 정보]")
                        if detail.get('mealroomyn') == 'TBC1300001':
                            facility_info.append("식당: 있음")
                        if detail.get('superyn') == 'TBC1300001':
                            facility_info.append("매점: 있음")
                        if detail.get('parkyn') == 'TBC1300001':
                            facility_info.append("주차장: 있음")
                        if detail.get('waitroomyn') == 'TBC1300001':
                            facility_info.append("유족대기실: 있음")
                        if detail.get('imparyn') == 'TBC1300001':
                            facility_info.append("장애인편의시설: 있음")
                        
                        # 가격 정보 (hallRent, commission, funeralItem)
                        # hallRent: 시설사용료 - API 원본 데이터 그대로 표시 (가공 없이, 표 형식)
                        if api_data.get('hallRent'):
                            facility_info.append("\n[시설사용료]")
                            # 표 헤더 (JavaScript와 동일한 형식)
                            facility_info.append("품종\t품명\t임대내용\t요금\t일수")
                            for item in api_data['hallRent']:
                                # API 원본 데이터 그대로 사용 (가공 없이)
                                # JavaScript: $td[0].text(row.tier2Nm), $td[1].text(row.item), $td[2].text(row.rentcontent), $td[3].text(row.facilityamtm)
                                tier2_nm = item.get('tier2Nm', '') or ''
                                item_name = item.get('item', '') or ''
                                rent_content = item.get('rentcontent', '') or ''
                                facility_amt = item.get('facilityamt', 0) or 0
                                facility_amtm = item.get('facilityamtm', '') or ''
                                
                                # 요금 표시: facilityamtm이 있으면 포맷된 문자열 사용, 없으면 facilityamt 사용
                                if facility_amtm and facility_amtm != "" and facility_amtm != "null":
                                    price_str = str(facility_amtm)  # 포맷된 요금 (예: "500,000")
                                elif facility_amt and facility_amt != 0:
                                    # 숫자를 포맷팅 (천 단위 쉼표)
                                    price_str = f"{facility_amt:,}"
                                else:
                                    price_str = ""
                                
                                # 일수는 facilityamtm이 요금으로 사용되므로 별도 일수 필드가 없을 수 있음
                                # API 구조상 일수는 별도 필드가 없을 수 있으므로 빈 문자열
                                days_str = ""
                                
                                # 원본 데이터 그대로 표시 (탭 구분, 가공 없이)
                                facility_info.append(f"{tier2_nm}\t{item_name}\t{rent_content}\t{price_str}\t{days_str}")
                            
                            if not api_data['hallRent']:
                                facility_info.append("가격 정보 없음")
                        
                        # commission: 서비스 항목 - API 원본 데이터 그대로 표시 (가공 없이, 표 형식)
                        if api_data.get('commission'):
                            facility_info.append("\n[서비스 항목]")
                            # 표 헤더 (JavaScript와 동일한 형식)
                            facility_info.append("품종\t품명\t서비스내용\t요금\t일수")
                            for item in api_data['commission']:
                                # API 원본 데이터 그대로 사용 (가공 없이)
                                # JavaScript: $td[0].text(row.tier2Nm), $td[1].text(row.item), $td[2].text(row.servcontent), $td[3].text(row.facilityamtm)
                                tier2_nm = item.get('tier2Nm', '') or ''
                                item_name = item.get('item', '') or ''
                                serv_content = item.get('servcontent', '') or ''
                                facility_amt = item.get('facilityamt', 0) or 0
                                facility_amtm = item.get('facilityamtm', '') or ''
                                
                                # 요금 표시: facilityamtm이 있으면 포맷된 문자열 사용, 없으면 facilityamt 사용
                                if facility_amtm and facility_amtm != "" and facility_amtm != "null":
                                    price_str = str(facility_amtm)  # 포맷된 요금
                                elif facility_amt and facility_amt != 0:
                                    price_str = f"{facility_amt:,}"
                                else:
                                    price_str = ""
                                
                                # 일수는 별도 필드가 없을 수 있음
                                days_str = ""
                                
                                # 원본 데이터 그대로 표시 (탭 구분, 가공 없이)
                                facility_info.append(f"{tier2_nm}\t{item_name}\t{serv_content}\t{price_str}\t{days_str}")
                            
                            if not api_data['commission']:
                                facility_info.append("가격 정보 없음")
                        
                        # funeralItem: 장사용품 - API 원본 데이터 그대로 표시 (가공 없이, 표 형식)
                        if api_data.get('funeralItem'):
                            facility_info.append("\n[장사용품]")
                            # 표 헤더 (JavaScript와 동일한 형식)
                            facility_info.append("품종\t품명\t규격/재질/원산지 등\t요금\t일수")
                            for item in api_data['funeralItem']:
                                # API 원본 데이터 그대로 사용 (가공 없이)
                                tier2_nm = item.get('tier2Nm', '') or ''
                                commodity = item.get('commodity', '') or ''
                                etc_info = item.get('etcinfo', '') or ''
                                comm_amt = item.get('commamt', 0) or 0
                                commamtm = item.get('commamtm', '') or ''
                                
                                # 요금 표시: commamtm이 있으면 포맷된 문자열 사용, 없으면 commamt 사용
                                if commamtm and commamtm != "" and commamtm != "null":
                                    price_str = str(commamtm)  # 포맷된 요금
                                elif comm_amt and comm_amt != 0:
                                    price_str = f"{comm_amt:,}"
                                else:
                                    price_str = ""
                                
                                # 일수는 별도 필드가 없을 수 있음
                                days_str = ""
                                
                                # 원본 데이터 그대로 표시 (탭 구분, 가공 없이)
                                facility_info.append(f"{tier2_nm}\t{commodity}\t{etc_info}\t{price_str}\t{days_str}")
                            
                            if not api_data['funeralItem']:
                                facility_info.append("가격 정보 없음")
                        
                        # 패키지 정보
                        if api_data.get('packageList'):
                            facility_info.append("\n[패키지 상품]")
                            for pkg in api_data['packageList'][:5]:  # 최대 5개만
                                if pkg.get('packagenm'):
                                    facility_info.append(f"  {pkg.get('packagenm', '')} - {pkg.get('funeraltypecd', '')} / 정가: {pkg.get('standardprice', 0):,}원 / 판매가: {pkg.get('saleprice', 0):,}원")
                        
                        page_text = '\n'.join(facility_info)
                        
                        # 데이터베이스에 저장
                        if self.db_config:
                            hall_id = self.save_funeral_hall_to_db(api_data)
                            if hall_id:
                                self.save_funeral_hall_prices_to_db(hall_id, api_data)
                        
                        # 테이블 데이터는 API 데이터로 구성
                        tables_data = []
                        
                        # 시설사용료 테이블
                        if api_data.get('hallRent'):
                            table_data = [['시설사용료 항목', '사용료내역', '요금(원)']]
                            for item in api_data['hallRent']:
                                item_name = item.get('item', '')
                                rent_content = item.get('rentcontent', '')
                                facility_amt = item.get('facilityamt', 0)
                                tier1_nm = item.get('tier1Nm', '')
                                tier2_nm = item.get('tier2Nm', '')
                                
                                if item_name and item_name != '-' and facility_amt > 0:
                                    table_data.append([
                                        f"{tier1_nm} - {tier2_nm}",
                                        f"{item_name} / {rent_content}",
                                        f"{facility_amt:,}"
                                    ])
                            if len(table_data) > 1:
                                tables_data.append(table_data)
                        
                        # 서비스 항목 테이블
                        if api_data.get('commission'):
                            table_data = [['서비스 항목', '서비스내역', '요금(원)']]
                            for item in api_data['commission']:
                                item_name = item.get('item', '')
                                serv_content = item.get('servcontent', '')
                                facility_amt = item.get('facilityamt', 0)
                                tier1_nm = item.get('tier1Nm', '')
                                tier2_nm = item.get('tier2Nm', '')
                                
                                if item_name and item_name != '-' and facility_amt > 0:
                                    table_data.append([
                                        f"{tier1_nm} - {tier2_nm}",
                                        f"{item_name} / {serv_content}",
                                        f"{facility_amt:,}"
                                    ])
                            if len(table_data) > 1:
                                tables_data.append(table_data)
                        
                        # 장사용품 테이블
                        if api_data.get('funeralItem'):
                            table_data = [['장사용품 분류', '품명/규격', '요금(원)']]
                            for item in api_data['funeralItem']:
                                commodity = item.get('commodity', '')
                                etc_info = item.get('etcinfo', '')
                                comm_amt = item.get('commamt', 0)
                                tier1_nm = item.get('tier1Nm', '')
                                tier2_nm = item.get('tier2Nm', '')
                                
                                if commodity and commodity != '-' and comm_amt > 0:
                                    table_data.append([
                                        f"{tier1_nm} - {tier2_nm}",
                                        f"{commodity} / {etc_info}",
                                        f"{comm_amt:,}"
                                    ])
                            if len(table_data) > 1:
                                tables_data.append(table_data)
                        
                        return {
                            'url': url,
                            'title': detail.get('companyname', '장례식장 정보'),
                            'text': page_text,
                            'tables': tables_data,
                            'timestamp': datetime.now().isoformat()
                        }
        
        # API로 가져오지 못한 경우 기존 방식 사용
        soup = BeautifulSoup(html, 'html.parser')
        
        # 스크립트, 스타일, 메뉴, 헤더, 푸터 제거
        for script in soup(["script", "style"]):
            script.decompose()
        
        # 메뉴, 헤더, 푸터 제거
        for elem in soup.find_all(['nav', 'header', 'footer']):
            elem.decompose()
        
        # 메인 콘텐츠 영역 먼저 찾기 (제거하기 전에)
        content_areas = []
        
        # 시설정보 페이지인 경우 - 실제 콘텐츠 영역 찾기
        if 'fac_view' in url:
            # 실제 페이지 구조: sub_container > sub_content > board_content > board_view > facinfo_view
            # board_content가 가장 적합한 콘텐츠 영역 (메뉴 제외)
            content_selectors = [
                soup.find('div', class_='board_content'),
                soup.find('div', class_='board_view'),
                soup.find('div', class_='facinfo_view'),
                soup.find('div', class_='facinfo_cont'),
                soup.find('div', id='sub_container'),
                soup.find('div', class_='sub_content'),
            ]
            for area in content_selectors:
                if area:
                    content_areas.append(area)
                    break
        
        # 가격정보 페이지인 경우
        elif 'fac_price' in url or 'fnlprc' in url or 'price' in url.lower():
            # 가격정보 관련 콘텐츠 영역 찾기
            content_selectors = [
                soup.find('div', id='sub_container'),
                soup.find('div', class_='sub_content'),
                soup.find('div', class_='board_content'),
                soup.find('div', class_='board_view'),
                soup.find('div', class_=re.compile(r'price|cost|charge', re.I)),
            ]
            for area in content_selectors:
                if area:
                    content_areas.append(area)
                    break
        
        # 콘텐츠 영역을 찾은 후에 메뉴/헤더/푸터 제거 (단순화)
        # 콘텐츠 영역 외부의 메뉴/헤더/푸터만 제거
        # 콘텐츠 영역의 부모 요소들 보호
        protected_elements = set()
        for area in content_areas:
            if area:
                # 콘텐츠 영역과 그 부모 요소들 보호
                protected_elements.add(area)
                parent = area.parent
                while parent and parent.name != 'body':
                    protected_elements.add(parent)
                    parent = parent.parent
        
        unwanted_selectors = [
            ('div', {'class': 'util_wrap'}),
            ('div', {'class': 'gnb_wrap'}),
            ('div', {'class': 'sub_gnb_wrap'}),
            ('div', {'class': 'menu_wrap'}),
        ]
        
        for tag, attrs in unwanted_selectors:
            elems = soup.find_all(tag, attrs=attrs)  # type: ignore
            for elem in elems:
                if elem and elem not in protected_elements:
                    # 보호된 요소의 자손이 아닌지 확인
                    is_protected = False
                    for protected in protected_elements:
                        if protected and elem in protected.find_all():
                            is_protected = True
                            break
                    if not is_protected:
                        elem.decompose()
        
        # 특정 요소 제거 (로고 등)
        for elem in soup.find_all(['h1', 'h2', 'h3']):
            elem_class = elem.get('class') or []
            class_str = str(elem_class) if isinstance(elem_class, list) else str(elem_class)
            if elem.find('a') and ('logo' in class_str.lower() or '로고' in elem.get_text()):
                elem.decompose()
        
        # 콘텐츠 영역을 찾지 못한 경우 body에서 직접 추출
        if not content_areas:
            body = soup.find('body')
            if body:
                # body에서 메뉴/헤더/푸터 제외한 나머지
                content_areas.append(body)
        
        # 콘텐츠 영역을 찾지 못한 경우 body에서 직접 추출
        if not content_areas:
            body = soup.find('body')
            if body:
                # body에서 메뉴/헤더/푸터 제외한 나머지
                content_areas.append(body)
        
        # 콘텐츠 영역에서 텍스트 추출
        page_text = ""
        if content_areas:
            for area in content_areas:
                text = area.get_text(separator='\n', strip=True)
                # 불필요한 빈 줄 제거 및 최소한의 필터링만 적용
                lines = []
                # 제거할 단독 키워드만 (메뉴/UI 요소)
                exclude_single_words = ['홈', '로고', '닫기', '검색', '전체메뉴']
                
                for line in text.split('\n'):
                    line_stripped = line.strip()
                    if line_stripped:
                        # 단독 키워드만 제거 (나머지는 모두 유지)
                        if line_stripped not in exclude_single_words:
                            lines.append(line)
                page_text = '\n'.join(lines)
                break  # 첫 번째 콘텐츠 영역만 사용
        else:
            # 콘텐츠 영역을 찾지 못한 경우 전체 텍스트에서 메뉴 관련 키워드 제거
            page_text = soup.get_text(separator='\n', strip=True)
            # 메뉴 관련 키워드가 포함된 줄 제거
            menu_keywords = ['장사정보서비스', '장사시설', '장례용품검색', 'e스카이러닝', 'e하늘소개', 
                           '이용안내', '개인정보처리방침', '저작권정책', '웹접근성마크', '보건복지부',
                           '한국장례문화진흥원', 'Copyright', 'All Rights Reserved', '로고', '닫기',
                           '검색', '전체메뉴', '원격지원요청', '국가상징', '하늘e 챗봇상담']
            lines = []
            for line in page_text.split('\n'):
                line_stripped = line.strip()
                if line_stripped and not any(keyword in line_stripped for keyword in menu_keywords):
                    lines.append(line)
            page_text = '\n'.join(lines)
        
        # 테이블 데이터 추출 (콘텐츠 영역 내의 테이블만)
        tables_data = []
        if content_areas:
            for area in content_areas:
                for table in area.find_all('table'):
                    table_data = []
                    rows = table.find_all('tr')
                    for row in rows:
                        cells = row.find_all(['td', 'th'])
                        row_data = [cell.get_text(strip=True) for cell in cells]
                        if row_data:
                            table_data.append(row_data)
                    if table_data:
                        tables_data.append(table_data)
        else:
            # 콘텐츠 영역을 찾지 못한 경우 모든 테이블 추출
            for table in soup.find_all('table'):
                table_data = []
                rows = table.find_all('tr')
                for row in rows:
                    cells = row.find_all(['td', 'th'])
                    row_data = [cell.get_text(strip=True) for cell in cells]
                    if row_data:
                        table_data.append(row_data)
                if table_data:
                    tables_data.append(table_data)
        
        return {
            'url': url,
            'title': soup.title.string if soup.title else '',
            'text': page_text,
            'tables': tables_data,
            'timestamp': datetime.now().isoformat()
        }
    
    def save_funeral_hall_to_db(self, api_data):
        """장례식장 정보를 데이터베이스에 저장"""
        if not self.db_conn:
            if not self.connect_db():
                return None
        
        try:
            detail = api_data.get('detail', {})
            if not detail:
                return None
            
            # 주소 파싱 (최적화된 구조)
            full_address = detail.get('fulladdress', '')
            addr_sido = ''
            addr_sigungu = ''
            addr_detail = ''
            
            # orgidnm에서 시/도와 시/군/구 추출
            if detail.get('orgidnm'):
                parts = detail.get('orgidnm', '').split()
                if len(parts) > 0:
                    addr_sido = parts[0][:20]  # 최대 20자
                if len(parts) > 1:
                    addr_sigungu = ' '.join(parts[1:])[:30]  # 최대 30자
            
            # fulladdress에서 상세 주소 추출
            if full_address:
                if addr_sido and addr_sigungu:
                    # 시/도와 시/군/구를 제외한 나머지 부분
                    addr_detail = full_address.replace(addr_sido, '').replace(addr_sigungu, '').strip()[:100]
                else:
                    addr_detail = full_address[:100]
            
            # 위도, 경도
            latitude = detail.get('latitude')
            longitude = detail.get('longitude')
            
            # 장례식장 정보 저장 또는 업데이트
            if not self.db_conn:
                return None
            cursor = self.db_conn.cursor()
            
            # 기존 장례식장 확인 (hall_name으로)
            hall_name = detail.get('companyname', '')
            if not hall_name:
                return None
            
            cursor.execute(
                "SELECT hall_id FROM funeral_hall WHERE hall_name = %s",
                (hall_name,)
            )
            existing = cursor.fetchone()
            
            # 개업일자 파싱 (YYYY-MM-DD 형식)
            businessdate = None
            if detail.get('businessdateS'):
                try:
                    from datetime import datetime
                    date_str = detail.get('businessdateS', '').strip()
                    if date_str and date_str != '':
                        businessdate = datetime.strptime(date_str, '%Y-%m-%d').date()
                except Exception as e:
                    # 날짜 파싱 실패 시 None 유지
                    pass
            
            # 빈소 수, 안치 능력, 주차 대수 파싱 (숫자만 추출)
            mortuaycnt = None
            if detail.get('mortuaycnt'):
                try:
                    mortuaycnt = int(detail.get('mortuaycnt'))
                except:
                    pass
            
            charnelabilitycnt = None
            if detail.get('charnelabilitycnt'):
                try:
                    charnelabilitycnt = int(detail.get('charnelabilitycnt'))
                except:
                    pass
            
            parkcnt = None
            if detail.get('parkcnt'):
                try:
                    parkcnt = int(detail.get('parkcnt'))
                except:
                    pass
            
            # 시설 정보 파싱 (TINYINT로 변환)
            mealroomyn = 1 if detail.get('mealroomyn') == 'TBC1300001' else 0
            superyn = 1 if detail.get('superyn') == 'TBC1300001' else 0
            parkyn = 1 if detail.get('parkyn') == 'TBC1300001' else 0
            waitroomyn = 1 if detail.get('waitroomyn') == 'TBC1300001' else 0
            imparyn = 1 if detail.get('imparyn') == 'TBC1300001' else 0
            
            # 빈소 수, 안치 능력 제한 (TINYINT UNSIGNED: 0-255)
            if mortuaycnt and mortuaycnt > 255:
                mortuaycnt = 255
            if charnelabilitycnt and charnelabilitycnt > 255:
                charnelabilitycnt = 255
            
            if existing:
                # 기존 데이터 확인
                hall_id = existing['hall_id']
                cursor.execute("""
                    SELECT tel, addr_sido, addr_sigungu, addr_detail, addr_full,
                           latitude, longitude, representativename, companyno, homepage,
                           businessdate, manageclassdiv, funeraltypecd, mortuaycnt,
                           charnelabilitycnt, parkcnt, mealroomyn, superyn, parkyn,
                           waitroomyn, imparyn
                    FROM funeral_hall
                    WHERE hall_id = %s
                """, (hall_id,))
                existing_data = cursor.fetchone()
                
                if not existing_data:
                    # 기존 데이터가 없으면 새로 삽입
                    cursor.execute("""
                        INSERT INTO funeral_hall 
                        (hall_name, tel, addr_sido, addr_sigungu, addr_detail, addr_full, 
                         latitude, longitude, representativename, companyno, homepage, businessdate,
                         manageclassdiv, funeraltypecd, mortuaycnt, charnelabilitycnt, parkcnt,
                         mealroomyn, superyn, parkyn, waitroomyn, imparyn)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    """, (
                        hall_name[:100],
                        (detail.get('telephone', '') or '')[:20] or None,
                        addr_sido or '',
                        addr_sigungu or '',
                        addr_detail or None,
                        (full_address or '')[:200] or None,
                        float(latitude) if latitude else None,
                        float(longitude) if longitude else None,
                        (detail.get('representativename', '') or '')[:50] or None,
                        (detail.get('companyno', '') or '')[:20] or None,
                        (detail.get('homepage', '') or '')[:150] or None,
                        businessdate,
                        (detail.get('manageclassdiv', '') or '')[:20] or None,
                        (detail.get('funeraltypecd', '') or '')[:20] or None,
                        mortuaycnt,
                        charnelabilitycnt,
                        parkcnt,
                        mealroomyn,
                        superyn,
                        parkyn,
                        waitroomyn,
                        imparyn
                    ))
                    hall_id = cursor.lastrowid
                    print(f"  장례식장 정보 저장: {hall_name} (hall_id: {hall_id})")
                else:
                    # 변경사항 확인
                    new_tel = (detail.get('telephone', '') or '')[:20] or None
                    new_addr_sido = addr_sido or ''
                    new_addr_sigungu = addr_sigungu or ''
                    new_addr_detail = addr_detail or None
                    new_addr_full = (full_address or '')[:200] or None
                    new_latitude = float(latitude) if latitude else None
                    new_longitude = float(longitude) if longitude else None
                    new_representativename = (detail.get('representativename', '') or '')[:50] or None
                    new_companyno = (detail.get('companyno', '') or '')[:20] or None
                    new_homepage = (detail.get('homepage', '') or '')[:150] or None
                    new_manageclassdiv = (detail.get('manageclassdiv', '') or '')[:20] or None
                    new_funeraltypecd = (detail.get('funeraltypecd', '') or '')[:20] or None
                    
                    # 변경사항이 있는지 확인
                    has_changes = (
                        (existing_data.get('tel') or '') != (new_tel or '') or
                        (existing_data.get('addr_sido') or '') != new_addr_sido or
                        (existing_data.get('addr_sigungu') or '') != new_addr_sigungu or
                        (existing_data.get('addr_detail') or '') != (new_addr_detail or '') or
                        (existing_data.get('addr_full') or '') != (new_addr_full or '') or
                        (existing_data.get('latitude') is None and new_latitude is not None) or
                        (existing_data.get('latitude') is not None and abs(float(existing_data.get('latitude') or 0) - (new_latitude or 0)) > 0.0001) or
                        (existing_data.get('longitude') is None and new_longitude is not None) or
                        (existing_data.get('longitude') is not None and abs(float(existing_data.get('longitude') or 0) - (new_longitude or 0)) > 0.0001) or
                        (existing_data.get('representativename') or '') != (new_representativename or '') or
                        (existing_data.get('companyno') or '') != (new_companyno or '') or
                        (existing_data.get('homepage') or '') != (new_homepage or '') or
                        (existing_data.get('businessdate') != businessdate if businessdate else existing_data.get('businessdate') is not None) or
                        (existing_data.get('manageclassdiv') or '') != (new_manageclassdiv or '') or
                        (existing_data.get('funeraltypecd') or '') != (new_funeraltypecd or '') or
                        existing_data.get('mortuaycnt') != mortuaycnt or
                        existing_data.get('charnelabilitycnt') != charnelabilitycnt or
                        existing_data.get('parkcnt') != parkcnt or
                        existing_data.get('mealroomyn') != mealroomyn or
                        existing_data.get('superyn') != superyn or
                        existing_data.get('parkyn') != parkyn or
                        existing_data.get('waitroomyn') != waitroomyn or
                        existing_data.get('imparyn') != imparyn
                    )
                
                if has_changes:
                    # 업데이트 (변경사항이 있는 경우만)
                    cursor.execute("""
                        UPDATE funeral_hall 
                        SET tel = %s, 
                            addr_sido = %s, 
                            addr_sigungu = %s, 
                            addr_detail = %s, 
                            addr_full = %s,
                            latitude = %s, 
                            longitude = %s,
                            representativename = %s,
                            companyno = %s,
                            homepage = %s,
                            businessdate = %s,
                            manageclassdiv = %s,
                            funeraltypecd = %s,
                            mortuaycnt = %s,
                            charnelabilitycnt = %s,
                            parkcnt = %s,
                            mealroomyn = %s,
                            superyn = %s,
                            parkyn = %s,
                            waitroomyn = %s,
                            imparyn = %s
                        WHERE hall_id = %s
                    """, (
                        new_tel,
                        new_addr_sido,
                        new_addr_sigungu,
                        new_addr_detail,
                        new_addr_full,
                        new_latitude,
                        new_longitude,
                        new_representativename,
                        new_companyno,
                        new_homepage,
                        businessdate,
                        new_manageclassdiv,
                        new_funeraltypecd,
                        mortuaycnt,
                        charnelabilitycnt,
                        parkcnt,
                        mealroomyn,
                        superyn,
                        parkyn,
                        waitroomyn,
                        imparyn,
                        hall_id
                    ))
                    print(f"  장례식장 정보 업데이트: {hall_name} (hall_id: {hall_id})")
                else:
                    print(f"  장례식장 정보 건너뜀 (변경사항 없음): {hall_name} (hall_id: {hall_id})")
            else:
                # 새로 삽입 (최적화된 필드만)
                cursor.execute("""
                    INSERT INTO funeral_hall 
                    (hall_name, tel, addr_sido, addr_sigungu, addr_detail, addr_full, 
                     latitude, longitude, representativename, companyno, homepage, businessdate,
                     manageclassdiv, funeraltypecd, mortuaycnt, charnelabilitycnt, parkcnt,
                     mealroomyn, superyn, parkyn, waitroomyn, imparyn)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """, (
                    hall_name[:100],
                    (detail.get('telephone', '') or '')[:20] or None,
                    addr_sido or '',
                    addr_sigungu or '',
                    addr_detail or None,
                    (full_address or '')[:200] or None,
                    float(latitude) if latitude else None,
                    float(longitude) if longitude else None,
                    (detail.get('representativename', '') or '')[:50] or None,
                    (detail.get('companyno', '') or '')[:20] or None,
                    (detail.get('homepage', '') or '')[:150] or None,
                    businessdate,
                    (detail.get('manageclassdiv', '') or '')[:20] or None,
                    (detail.get('funeraltypecd', '') or '')[:20] or None,
                    mortuaycnt,
                    charnelabilitycnt,
                    parkcnt,
                    mealroomyn,
                    superyn,
                    parkyn,
                    waitroomyn,
                    imparyn
                ))
                hall_id = cursor.lastrowid
                print(f"  장례식장 정보 저장: {hall_name} (hall_id: {hall_id})")
            
            self.db_conn.commit()
            cursor.close()
            return hall_id
            
        except Exception as e:
            print(f"  장례식장 정보 저장 오류: {e}")
            import traceback
            traceback.print_exc()
            if self.db_conn:
                self.db_conn.rollback()
            return None
    
    def save_funeral_hall_prices_to_db(self, hall_id, api_data):
        """장례식장 가격 정보를 데이터베이스에 저장"""
        if not self.db_conn:
            if not self.connect_db():
                return False
        
        if not hall_id:
            return False
        
        try:
            if not self.db_conn:
                return False
            cursor = self.db_conn.cursor()
            
            # category_id 매핑 (category_code로 조회) - 한 번만 조회
            category_map = {}
            cursor.execute("SELECT category_id, category_code FROM price_category WHERE is_active = 1")
            for row in cursor.fetchall():
                category_map[row['category_code']] = row['category_id']
            
            # 배치 INSERT를 위한 데이터 준비
            batch_data = []
            
            # 가격 파싱 헬퍼 함수
            def parse_price(amt, amtm):
                """가격 파싱 헬퍼 함수"""
                if amtm and amtm != "" and amtm != "null":
                    price_str = str(amtm).replace(',', '').replace('원', '').strip()
                    try:
                        return float(price_str) if price_str else 0
                    except:
                        return float(amt) if amt else 0
                else:
                    return float(amt) if amt else 0
            
            # 디버깅: API 데이터 확인
            if not api_data.get('hallRent') and not api_data.get('commission') and not api_data.get('funeralItem'):
                print(f"  ⚠ API에서 가격 정보를 가져오지 못했습니다. (hallRent: {len(api_data.get('hallRent', []))}, commission: {len(api_data.get('commission', []))}, funeralItem: {len(api_data.get('funeralItem', []))})")
            
            # 1. 시설사용료 (hallRent) -> FACILITY
            if 'FACILITY' in category_map and api_data.get('hallRent'):
                category_id = category_map['FACILITY']
                for item in api_data['hallRent']:
                    product_type = (item.get('tier2Nm', '') or '')[:50]  # 품종
                    product_name = (item.get('item', '') or '')[:100]  # 품명
                    service_content = (item.get('rentcontent', '') or '')[:150]  # 서비스내용
                    facility_amt = item.get('facilityamt', 0)
                    facility_amtm = item.get('facilityamtm', '')
                    
                    if product_name and product_name != '-' and facility_amt and facility_amt != 0:
                        price = parse_price(facility_amt, facility_amtm)
                        if price > 0:
                            batch_data.append((
                                hall_id,
                                category_id,
                                product_type if product_type else None,
                                product_name,
                                service_content if service_content and service_content != '-' else None,
                                price
                            ))
            
            # 2. 서비스 항목 (commission) -> RITUAL
            if 'RITUAL' in category_map and api_data.get('commission'):
                category_id = category_map['RITUAL']
                for item in api_data['commission']:
                    product_type = (item.get('tier2Nm', '') or '')[:50]  # 품종
                    product_name = (item.get('item', '') or '')[:100]  # 품명
                    service_content = (item.get('servcontent', '') or '')[:150]  # 서비스내용
                    facility_amt = item.get('facilityamt', 0)
                    facility_amtm = item.get('facilityamtm', '')
                    
                    if product_name and product_name != '-' and facility_amt and facility_amt != 0:
                        price = parse_price(facility_amt, facility_amtm)
                        if price > 0:
                            batch_data.append((
                                hall_id,
                                category_id,
                                product_type if product_type else None,
                                product_name,
                                service_content if service_content and service_content != '-' else None,
                                price
                            ))
            
            # 3. 장사용품 (funeralItem) -> GOODS
            if 'GOODS' in category_map and api_data.get('funeralItem'):
                category_id = category_map['GOODS']
                for item in api_data['funeralItem']:
                    product_type = (item.get('tier2Nm', '') or '')[:50]  # 품종
                    product_name = (item.get('commodity', '') or '')[:100]  # 품명
                    service_content = (item.get('etcinfo', '') or '')[:150]  # 서비스내용
                    comm_amt = item.get('commamt', 0)
                    commamtm = item.get('commamtm', '')
                    
                    if product_name and product_name != '-' and comm_amt and comm_amt != 0:
                        price = parse_price(comm_amt, commamtm)
                        if price > 0:
                            batch_data.append((
                                hall_id,
                                category_id,
                                product_type if product_type else None,
                                product_name,
                                service_content if service_content and service_content != '-' else None,
                                price
                            ))
            
            # 배치 INSERT (효율성 향상)
            if batch_data:
                # 기존 가격 정보 조회 (중복 체크용)
                cursor.execute("""
                    SELECT category_id, product_name, service_content, price
                    FROM funeral_hall_price
                    WHERE hall_id = %s
                """, (hall_id,))
                existing_prices = cursor.fetchall()
                
                # 기존 가격 정보를 딕셔너리로 변환 (빠른 조회용)
                existing_price_map = {}
                for ep in existing_prices:
                    key = (
                        ep['category_id'],
                        ep['product_name'] or '',
                        ep['service_content'] or ''
                    )
                    existing_price_map[key] = ep['price']
                
                # 중복 제거 및 새 데이터만 필터링
                seen = set()
                new_batch_data = []
                skipped_count = 0
                
                for item in batch_data:
                    # (hall_id, category_id, product_name, service_content) 조합으로 중복 체크
                    batch_key = (item[0], item[1], item[3], item[4] if item[4] else '')
                    
                    # 배치 내 중복 체크
                    if batch_key in seen:
                        continue
                    seen.add(batch_key)
                    
                    # 기존 데이터와 비교
                    check_key = (
                        item[1],  # category_id
                        item[3],  # product_name
                        item[4] if item[4] else ''  # service_content
                    )
                    
                    # 기존 데이터가 있고 가격도 동일하면 skip
                    if check_key in existing_price_map:
                        existing_price = float(existing_price_map[check_key])
                        new_price = float(item[5])
                        if abs(existing_price - new_price) < 0.01:  # 가격이 동일하면 (소수점 오차 고려)
                            skipped_count += 1
                            continue
                    
                    # 새로운 데이터 또는 가격이 변경된 데이터만 추가
                    new_batch_data.append(item)
                
                # 새 데이터 또는 변경된 데이터만 INSERT (ON DUPLICATE KEY UPDATE 사용)
                if new_batch_data:
                    # 배치 INSERT (중복 시 업데이트)
                    cursor.executemany("""
                        INSERT INTO funeral_hall_price 
                        (hall_id, category_id, product_type, product_name, service_content, price)
                        VALUES (%s, %s, %s, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE 
                            product_type = VALUES(product_type),
                            service_content = VALUES(service_content),
                            price = VALUES(price),
                            updated_at = CURRENT_TIMESTAMP
                    """, new_batch_data)
                    
                    saved_count = len(new_batch_data)
                    if skipped_count > 0:
                        print(f"  가격 정보 저장 완료: {saved_count}개 항목 저장, {skipped_count}개 건너뜀 (기존과 동일)")
                    else:
                        print(f"  가격 정보 저장 완료: {saved_count}개 항목")
                else:
                    saved_count = 0
                    if skipped_count > 0:
                        print(f"  가격 정보 건너뜀: 모든 {skipped_count}개 항목이 기존과 동일")
                    else:
                        print(f"  가격 정보 없음: 저장할 항목이 없습니다.")
            else:
                saved_count = 0
            
            self.db_conn.commit()
            cursor.close()
            return True
            
        except Exception as e:
            print(f"  가격 정보 저장 오류: {e}")
            import traceback
            traceback.print_exc()
            if self.db_conn:
                self.db_conn.rollback()
            return False
    
    def crawl_page(self, url, params=None):
        """단일 페이지 크롤링"""
        # params를 포함한 URL 정규화
        normalized_url = self.normalize_url(url, params)
        
        # 이미 방문한 URL이면 스킵
        if normalized_url in self.visited_urls:
            if params:
                print(f"  이미 방문한 페이지 (옵션 조합): {normalized_url}")
            return None
        
        self.visited_urls.add(normalized_url)
        
        if params:
            print(f"크롤링 (옵션 조합): {normalized_url}")
        else:
            print(f"크롤링: {normalized_url}")
        
        html = self.get_page(normalized_url, params=params)
        if not html:
            return None
        
        # 페이지 데이터 추출
        page_data = self.extract_page_data(html, normalized_url)
        self.crawled_data.append(page_data)
        
        return html
    
    def crawl_with_options(self, base_url, options):
        """모든 옵션 조합으로 크롤링"""
        if not options:
            return
        
        option_names = list(options.keys())
        option_values_list = [options[name] for name in option_names]
        
        # 모든 조합 생성 (빈 조합 제외)
        combinations = []
        for combination in product(*option_values_list):
            params = {}
            for idx, option_name in enumerate(option_names):
                if combination[idx]['value']:
                    params[option_name] = combination[idx]['value']
            # 빈 조합은 제외
            if params:
                combinations.append(params)
        
        if not combinations:
            print(f"  옵션 조합이 없습니다.")
            return
        
        print(f"\n  총 {len(combinations)}개의 옵션 조합 발견")
        print(f"  옵션: {option_names}")
        
        for idx, combo in enumerate(combinations, 1):
            print(f"  [{idx}/{len(combinations)}] 옵션 조합 크롤링: {combo}")
            # 옵션 조합으로 페이지 크롤링
            html = self.crawl_page(base_url, params=combo)
            if html:
                print(f"    ✓ 옵션 조합 페이지 크롤링 완료")
            else:
                print(f"    ✗ 옵션 조합 페이지 크롤링 실패 또는 이미 방문함")
            time.sleep(0.5)  # 서버 부하 방지
    
    def crawl_all(self, start_url):
        """모든 페이지 크롤링 (BFS 방식)"""
        print("=" * 80)
        print("전체 사이트 크롤링 시작")
        print("=" * 80)
        print(f"시작 URL: {start_url}\n")
        
        # 큐에 시작 URL 추가
        url_queue = deque([start_url])
        max_depth = 3  # 최대 깊이 제한
        depth = 0
        
        while url_queue and depth < max_depth:
            current_level_size = len(url_queue)
            depth += 1
            print(f"\n{'='*80}")
            print(f"깊이 {depth} 크롤링 시작 (대기 중인 URL: {current_level_size}개)")
            print(f"{'='*80}\n")
            
            for _ in range(current_level_size):
                if not url_queue:
                    break
                
                current_url = url_queue.popleft()
                
                # 페이지 크롤링
                html = self.crawl_page(current_url)
                if not html:
                    continue
                
                # 페이지에서 링크 추출
                links = self.extract_all_links(html, current_url)
                
                # 새로운 링크를 큐에 추가
                for link in links:
                    normalized_link_url = self.normalize_url(link['url'], None)
                    if normalized_link_url not in self.visited_urls:
                        url_queue.append(link['url'])
                
                # 옵션 추출 및 모든 조합 크롤링
                options = self.extract_all_options(html)
                if options:
                    print(f"  발견된 옵션: {list(options.keys())}")
                    self.crawl_with_options(current_url, options)
                
                time.sleep(0.3)  # 서버 부하 방지
        
        print(f"\n{'='*80}")
        print(f"크롤링 완료!")
        print(f"총 {len(self.visited_urls)}개 페이지 크롤링")
        print(f"{'='*80}\n")
    
    def crawl_facility_list(self, start_url):
        """장례식장 리스트 페이지에서 시설정보와 가격정보 크롤링"""
        print("=" * 80)
        print("장례식장 리스트 크롤링 시작")
        print("=" * 80)
        print(f"시작 URL: {start_url}\n")
        
        # 방문한 리스트 페이지 URL 추적
        visited_list_pages = set()
        list_page_queue = deque([start_url])
        
        # 수집된 장례식장 정보
        facilities_data = {}
        
        # 리스트 페이지 크롤링 (최대 111페이지, 총 1106건)
        max_pages = 111
        page_count = 0
        
        while list_page_queue and page_count < max_pages:
            current_list_url = list_page_queue.popleft()
            
            # 해시가 있는 경우와 없는 경우 모두 처리
            # 해시가 없으면 추가 (JavaScript가 자동으로 추가하는 해시)
            base_url = current_list_url.split('#')[0]
            if '#{' not in current_list_url:
                # 해시 추가: #{"page":1} 형식
                page_num = page_count + 1
                current_list_url = f'{base_url}#{{"page":{page_num}}}'
            
            # 방문 체크는 해시 포함 URL로
            if current_list_url in visited_list_pages:
                continue
            
            visited_list_pages.add(current_list_url)
            page_count += 1
            
            print(f"\n{'='*80}")
            print(f"[{page_count}/{max_pages}] 리스트 페이지 크롤링: {current_list_url}")
            print(f"{'='*80}\n")
            
            # 해시에서 페이지 번호 추출
            page_num = page_count
            if '#' in current_list_url:
                hash_part = current_list_url.split('#')[1]
                try:
                    import urllib.parse
                    decoded_hash = urllib.parse.unquote(hash_part)
                    hash_data = json.loads(decoded_hash)
                    page_num = hash_data.get('page', page_count)
                except:
                    pass
            
            # 일반 HTTP 요청 모드인 경우 API 직접 호출 시도
            html = None  # 초기화
            api_response = None  # API 응답 저장
            
            if not self.use_selenium:
                print(f"  API로 데이터 가져오기 시도 (페이지 {page_num})...")
                api_data = self.get_facility_list_api(page=page_num, page_size=12)
                api_response = api_data  # 나중에 페이지네이션에 사용
                
                if api_data:
                    # JSON 응답인 경우
                    if isinstance(api_data, dict):
                        print(f"  ✓ API JSON 데이터 수신!")
                        
                        # 빈 리스트인 경우 체크
                        total_count = api_data.get('cnt', 0)
                        page_size = 12
                        total_pages = math.ceil(total_count / page_size) if total_count > 0 else 0
                        
                        if page_num > total_pages:
                            print(f"  ⚠ 페이지 {page_num}는 존재하지 않습니다. (최대 {total_pages}페이지, 총 {total_count}개 항목)")
                            facilities = []
                            continue
                        
                        facilities = self.extract_facilities_from_api_data(api_data, current_list_url)
                        if facilities:
                            print(f"  ✓ API에서 발견된 장례식장: {len(facilities)}개")
                        else:
                            print(f"  ✗ API 데이터에서 장례식장을 찾을 수 없습니다.")
                            # 디버깅: API 응답은 콘솔에만 출력
                            if page_count == 1:
                                print(f"  디버깅: API 응답 확인 필요")
                            facilities = []
                    else:
                        # HTML 응답인 경우
                        print(f"  ✓ API HTML 응답 수신 (길이: {len(api_data)} bytes)")
                        html = api_data  # HTML로 사용
                        facilities = self.extract_facility_items(html, current_list_url)
                else:
                    # API 실패 시 일반 HTML 가져오기
                    print(f"  ✗ API 호출 실패, HTML 파싱으로 폴백...")
                    html = self.get_page(current_list_url.split('#')[0])
                    if html:
                        facilities = self.extract_facility_items(html, current_list_url)
                    else:
                        facilities = []
            else:
                # Selenium 모드: 해시 포함 URL 사용
                html = self.get_page(current_list_url, wait_for_element='facList' if self.use_selenium else None)
                if not html:
                    print(f"  페이지를 가져올 수 없습니다.")
                    continue
                facilities = self.extract_facility_items(html, current_list_url)
            
            print(f"  발견된 장례식장: {len(facilities)}개")
            
            # 각 장례식장의 시설정보와 가격정보 크롤링
            for idx, facility in enumerate(facilities, 1):
                facility_name = facility['name']
                info_url = facility.get('info_url')
                price_url = facility.get('price_url')
                fac_id = facility.get('fac_id')
                
                print(f"\n  [{idx}/{len(facilities)}] {facility_name}")
                
                # 시설정보 크롤링 (시설정보 버튼 클릭)
                info_data = None
                if info_url:
                    print(f"    → 시설정보 버튼 클릭: {info_url}")
                    info_html = self.get_page(info_url)
                    if info_html:
                        info_data = self.extract_page_data(info_html, info_url)
                        self.visited_urls.add(info_url)
                        time.sleep(0.5)  # 서버 부하 방지
                    else:
                        print(f"    ✗ 시설정보 페이지를 가져올 수 없습니다.")
                elif fac_id:
                    # facId로 URL 생성
                    menu_id = 'M0001000100000000'
                    info_url = f"{self.base_url}/portal/esky/fnlfac/fac_view.do?menuId={menu_id}&facId={fac_id}"
                    print(f"    → 시설정보 버튼 클릭 (생성): {info_url}")
                    info_html = self.get_page(info_url)
                    if info_html:
                        info_data = self.extract_page_data(info_html, info_url)
                        self.visited_urls.add(info_url)
                        time.sleep(0.5)
                
                # 가격정보는 fac_view 페이지의 API에서 이미 가져오므로 별도 호출 불필요
                # extract_page_data에서 fac_view 페이지를 처리할 때 price_info.ajax API를 호출하여
                # 시설정보와 가격정보를 모두 가져옵니다.
                price_data = None
                # info_data에 이미 가격 정보가 포함되어 있으므로 별도 처리 불필요
                
                # 장례식장별로 데이터 저장 (데이터베이스에만 저장)
                # info_data에 이미 시설정보와 가격정보가 모두 포함되어 있음
                # 중복 체크는 fac_id나 이름으로
                key = fac_id if fac_id else facility_name
                if key not in facilities_data:
                    facilities_data[key] = {
                        'name': facility_name,
                        'info': info_data,
                        'price': None  # 가격 정보는 info_data에 포함됨
                    }
                else:
                    # 이미 있는 경우 업데이트 (더 완전한 정보로)
                    if info_data and not facilities_data[key]['info']:
                        facilities_data[key]['info'] = info_data
            
            # 페이지네이션 링크 추출
            # API 모드인 경우 API 응답에서 페이지 정보 추출
            if not self.use_selenium and api_response and isinstance(api_response, dict):
                # API 응답에서 총 개수와 페이지 정보 추출
                total_count = api_response.get('cnt', 0)
                page_size = 12
                total_pages = math.ceil(total_count / page_size) if total_count > 0 else 0
                
                # 빈 리스트인 경우 (존재하지 않는 페이지)
                if not facilities and page_num > total_pages:
                    print(f"  ⚠ 페이지 {page_num}는 존재하지 않습니다. (최대 {total_pages}페이지)")
                    continue
                
                print(f"  총 {total_count}개 항목, {total_pages}페이지")
                
                # 빈 리스트인 경우 (존재하지 않는 페이지)
                if not facilities and page_num > total_pages:
                    print(f"  ⚠ 페이지 {page_num}는 존재하지 않습니다. (최대 {total_pages}페이지)")
                    continue
                
                # 다음 페이지들 생성 (최대 페이지 수를 초과하지 않도록)
                base_url = current_list_url.split('#')[0]
                for next_page in range(2, min(total_pages + 1, max_pages + 1)):
                    next_url = f'{base_url}#{{"page":{next_page}}}'
                    if next_url not in visited_list_pages:
                        list_page_queue.append(next_url)
            elif html:
                # HTML에서 페이지네이션 링크 추출
                pagination_links = self.extract_pagination_links(html, current_list_url)
                for page_link in pagination_links:
                    # 해시가 없으면 추가
                    if '#{' not in page_link:
                        base_link = page_link.split('#')[0]
                        # 페이지 번호 추출 시도
                        try:
                            # URL에서 페이지 번호 찾기
                            parsed = urlparse(page_link)
                            query_params = parse_qs(parsed.query)
                            page_num = query_params.get('page', [None])[0] or query_params.get('pageNo', [None])[0] or query_params.get('currentPage', [None])[0]
                            if page_num:
                                page_link = f'{base_link}#{{"page":{page_num}}}'
                        except:
                            pass
                    
                    if page_link not in visited_list_pages:
                        list_page_queue.append(page_link)
            
            # 페이지 번호로 직접 다음 페이지 생성 시도
            # JavaScript 해시에서 페이지 번호 추출
            if '#' in current_list_url:
                hash_part = current_list_url.split('#')[1]
                try:
                    # URL 디코딩
                    import urllib.parse
                    decoded_hash = urllib.parse.unquote(hash_part)
                    # JSON 파싱
                    hash_data = json.loads(decoded_hash)
                    current_page = hash_data.get('page', page_count)
                    if current_page < max_pages:
                        next_page = current_page + 1
                        # 해시 형식: #{"page":2}
                        next_url = f'{base_url}#{{"page":{next_page}}}'
                        if next_url not in visited_list_pages:
                            list_page_queue.append(next_url)
                except Exception as e:
                    # 해시 파싱 실패 시 단순히 페이지 번호 증가
                    if page_count < max_pages:
                        next_url = f'{base_url}#{{"page":{page_count + 1}}}'
                        if next_url not in visited_list_pages:
                            list_page_queue.append(next_url)
            
            time.sleep(0.5)  # 서버 부하 방지
        
        print(f"\n{'='*80}")
        print(f"크롤링 완료!")
        print(f"총 {len(facilities_data)}개 장례식장 정보 수집")
        print(f"총 {page_count}개 리스트 페이지 크롤링")
        print(f"{'='*80}\n")
        
        return facilities_data
    


def main():
    """메인 함수"""
    print("=" * 80)
    print("e하늘장사정보서비스 장례식장 크롤러")
    print("=" * 80)
    print("\n이 크롤러는 장례식장 리스트 페이지에서 시설정보와 가격정보를 크롤링합니다.")
    print("주의: 서버 부하를 고려하여 적절한 딜레이를 두고 크롤링합니다.\n")
    
    try:
        confirm = input("크롤링을 시작하시겠습니까? (y/n): ").strip().lower()
    except (EOFError, KeyboardInterrupt):
        print("\n취소되었습니다.")
        return
    
    if confirm != 'y':
        print("취소되었습니다.")
        return
    
    # Selenium 사용 여부 확인 (기본값: False - 일반 HTTP 요청 사용)
    use_selenium = False
    if SELENIUM_AVAILABLE:
        try:
            sel_input = input("Selenium을 사용하시겠습니까? (y/n) [n]: ").strip().lower() or "n"
            use_selenium = (sel_input == 'y')
        except:
            use_selenium = False  # 기본값: 일반 HTTP 요청
    
    crawler = EskyCrawler(use_selenium=use_selenium)
    
    try:
        # 메인 페이지에서 메뉴로 이동
        main_url = "https://15774129.go.kr/portal/esky/main/main.do"
        menu_text = "장사시설/장례용품가격"
        
        print(f"\n메인 페이지에서 '{menu_text}' 메뉴로 이동 중...")
        menu_url = crawler.navigate_to_menu(main_url, menu_text)
        
        if not menu_url:
            print("메뉴 링크를 찾을 수 없어 기본 URL을 사용합니다.")
            menu_url = "https://15774129.go.kr/portal/esky/fnlfac/fac_list.do?menuId=M0001000100000000"
        
        # 해시가 없으면 추가 (JavaScript가 자동으로 리다이렉트하는 해시)
        if '#{' not in menu_url:
            # URL에 해시 추가: #{"page":1}
            start_url = f'{menu_url}#{{"page":1}}'
        else:
            start_url = menu_url
        
        print(f"\n크롤링 시작 URL: {start_url}")
        if crawler.use_selenium:
            print("Selenium 모드로 크롤링합니다.")
        else:
            print("일반 HTTP 요청 모드로 크롤링합니다.")
        
        # 크롤링 시작
        facilities_data = crawler.crawl_facility_list(start_url)
        
        # 결과 요약 출력
        print(f"\n크롤링 완료: 총 {len(facilities_data)}개 장례식장 정보 수집")
        
        print("\n" + "=" * 80)
        print("크롤링 완료")
        print("=" * 80)
        
    except KeyboardInterrupt:
        print("\n\n사용자에 의해 중단되었습니다.")
    except Exception as e:
        print(f"\n오류 발생: {e}")
        import traceback
        traceback.print_exc()
    finally:
        crawler.close_db()
        crawler.close_selenium()
        crawler.close_selenium()


if __name__ == "__main__":
    main()

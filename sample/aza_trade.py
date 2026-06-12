import time
import requests
import json
import os
import csv
from datetime import datetime
from config_pro import *
from core_math import fetch, btc_ok

# ==========================================
# ⚙️ ВЫБОР СТРАТЕГИИ (ШВЕЙЦАРСКИЙ НОЖ)
# ==========================================
STRATEGY_MODE = 4  
# 1 - Серфер: Нет TP, только Трейлинг-стоп (для жадных).
# 2 - Гибрид: На TP продает 50%, остаток тянет Трейлингом (баланс).
# 3 - Умный Серфер: Во флэте Снайпер, при сильном тренде Серфер.
# 4 - Умный Гибрид: Во флэте Снайпер, при сильном тренде Гибрид (РЕКОМЕНДУЮ).

TREND_ADX = 30          # Порог ADX. Выше 30 = Сильный тренд (Ракета)
TRAILING_STEP = 0.985   # Отступ Трейлинг-стопа от пика цены (1.5%)

# ==========================================
# 💾 СЕРВИСНЫЕ ПЕРЕМЕННЫЕ И ПАМЯТЬ
# ==========================================
SYMBOLS = []
last_symbol_update = 0 
positions = {}
last_heartbeat = 0

start_of_day_usdt = 0.0
daily_profit_usdt = 0.0
daily_fees_usdt = 0.0
daily_trades = 0
daily_wins = 0
daily_losses = 0
last_summary_date = None

def get_time():
    return datetime.now(TZ_EKAT).strftime('%H:%M:%S')

def load_positions():
    global positions
    if os.path.exists(POSITIONS_FILE):
        try:
            with open(POSITIONS_FILE, 'r') as f:
                positions = json.load(f)
            print(f"[{get_time()}] 💾 Память загружена: {len(positions)} активных сделок.")
        except: positions = {}

def save_positions():
    with open(POSITIONS_FILE, 'w') as f:
        json.dump(positions, f, indent=2)

def log_trade_csv(symbol, entry, exit_price, pnl, profit_usd, reason):
    try:
        file_exists = os.path.isfile(CSV_LOG_FILE)
        with open(CSV_LOG_FILE, mode='a', newline='', encoding='utf-8') as f:
            writer = csv.writer(f)
            if not file_exists:
                writer.writerow(['Date', 'Symbol', 'Entry', 'Exit', 'Net_PnL_%', 'Net_Profit_USD', 'Reason'])
            date_str = datetime.now(TZ_EKAT).strftime('%Y-%m-%d %H:%M:%S')
            writer.writerow([date_str, symbol, entry, exit_price, round(pnl, 2), round(profit_usd, 2), reason])
    except Exception as e: print(f"[{get_time()}] ERROR CSV: {e}")

def send_tg(msg, parse_mode=None):
    try:
        url = f"https://api.telegram.org/bot{TG_TOKEN}/sendMessage"
        payload = {'chat_id': TG_CHAT_ID, 'text': msg}
        if parse_mode: payload['parse_mode'] = parse_mode
        requests.post(url, data=payload, timeout=5)
    except: pass

# ==========================================
# 🛡 ТОРГОВЫЕ ОПЕРАЦИИ (ПОКУПКА / ЧАСТИЧНАЯ ПРОДАЖА)
# ==========================================
def update_dynamic_symbols():
    global SYMBOLS, last_symbol_update
    if time.time() - last_symbol_update < UPDATE_INTERVAL and last_symbol_update != 0: return
    try:
        # 1. Получаем все рынки с биржи
        markets = exchange.load_markets()
        tickers = exchange.fetch_tickers()
        
        candidates = []
        
        for symbol, data in markets.items():
            # Фильтруем: только USDT пары, только активные, исключаем стейблкоины
            if symbol.endswith('/USDT') and data['active'] and 'USDC' not in symbol:
                ticker = tickers.get(symbol)
                if not ticker: continue
                
                vol_24h = ticker.get('quoteVolume', 0)
                # 2. Фильтр по объему (имитируем вайтлист биржи): 
                # Берем только монеты с оборотом более 5 млн USDT в сутки
                if vol_24h > 5_000_000:
                    high = ticker.get('high', 0)
                    low = ticker.get('low', 0)
                    if low > 0:
                        # 3. Считаем волатильность за 24 часа
                        volatility = ((high - low) / low) * 100
                        candidates.append((symbol, volatility))
        
        # 4. Сортируем по волатильности (самые "летающие" монеты)
        candidates.sort(key=lambda x: x[1], reverse=True)
        
        # Забираем ТОП-30
        SYMBOLS = [p[0] for p in candidates[:30]]
        
        last_symbol_update = time.time()
        print(f"[{get_time()}] 🔥 Сканер выбрал ТОП-30 волатильных монет из ликвидного списка Bybit.")
    except Exception as e: 
        print(f"[{get_time()}] ERROR EXCHANGE SCANNER: {e}")

def buy(symbol, price, sl, tp):
    global positions
    try:
        balance = exchange.fetch_balance()['total']['USDT']
        risk_amount = balance * RISK
        size = risk_amount / (price - sl)
        cost = size * price
        
        max_cost = balance * 0.30
        if cost > max_cost: cost = max_cost
        
        free_usdt = exchange.fetch_balance()['free'].get('USDT', 0)
        if cost > free_usdt: cost = free_usdt * 0.98
        if cost < 5: return 
            
        qty = float(exchange.amount_to_precision(symbol, cost / price))
        exchange.create_market_buy_order(symbol, qty)
        
        positions[symbol] = {
            'entry': price, 'sl': sl, 'tp': tp, 
            'qty': qty, 'be_activated': False, 
            'trailing_active': False, 'half_sold': False, 'time': get_time()
        }
        save_positions()
        
        log_msg = f"🟢 ВХОД: {symbol} | Цена: {price} | Объем: {round(cost, 2)}$"
        print(f"[{get_time()}] {log_msg}")
        send_tg(f"🟢 <b>ВХОД: {symbol}</b>\nЦена: {price}\nSL: {round(sl, 4)}\nTP: {round(tp, 4)}\nОбъем: {round(cost, 2)}$", parse_mode="HTML")
    except Exception as e: print(f"[{get_time()}] ERROR BUY {symbol}: {e}")

def sell(symbol, price, reason, portion=1.0):
    global positions, daily_profit_usdt, daily_fees_usdt, daily_trades, daily_wins, daily_losses
    pos = positions[symbol]
    try:
        base_coin = symbol.split('/')[0]
        actual_qty = exchange.fetch_balance()['free'].get(base_coin, 0)
        
        # Расчет объема продажи (100% или 50% для Гибрида)
        target_qty = actual_qty if portion >= 1.0 else (pos['qty'] * portion)
        
        # ==========================================
        #    ЗАЩИТА ОТ "ПЫЛИ" И ЗАЦИКЛИВАНИЯ
        # ==========================================
        if (target_qty * price) < 1.0:
            print(f"[{get_time()}] 🧹 {symbol}: Монет на балансе нет (или осталась пыль). Удаляю сделку-призрака.")
            if symbol in positions: del positions[symbol]
            save_positions()
            return 
        
        sell_qty = float(exchange.amount_to_precision(symbol, min(target_qty, actual_qty)))
        
        if sell_qty > 0: exchange.create_market_sell_order(symbol, sell_qty)
        
        entry_cost_usdt = pos['entry'] * sell_qty
        exit_value_usdt = price * sell_qty
        total_fees = (entry_cost_usdt * 0.001) + (exit_value_usdt * 0.001)
        daily_fees_usdt += total_fees
        
        net_profit_usdt = (exit_value_usdt - entry_cost_usdt) - total_fees
        net_pnl_percent = (net_profit_usdt / entry_cost_usdt) * 100
        
        log_trade_csv(symbol, pos['entry'], price, net_pnl_percent, net_profit_usdt, reason)
        
        daily_profit_usdt += net_profit_usdt
        if portion >= 1.0: 
            daily_trades += 1
            if net_profit_usdt > 0: daily_wins += 1
            else: daily_losses += 1
        
        emoji = "✅" if net_profit_usdt > 0 else ("➖" if portion < 1.0 else "❌")
        
        log_msg = f"{emoji} ВЫХОД: {symbol} | {reason} | Объем: {int(portion*100)}% | Net PnL: {round(net_pnl_percent, 2)}% ({round(net_profit_usdt, 2)}$)"
        print(f"[{get_time()}] {log_msg}")
        send_tg(f"{emoji} <b>ВЫХОД: {symbol}</b>\nПричина: {reason} ({int(portion*100)}%)\nЧИСТЫЙ PnL: {round(net_pnl_percent, 2)}% ({round(net_profit_usdt, 2)}$)", parse_mode="HTML")
        
        if portion >= 1.0:
            if symbol in positions: del positions[symbol]
        else:
            positions[symbol]['qty'] -= sell_qty
            positions[symbol]['half_sold'] = True
            positions[symbol]['be_activated'] = True
        save_positions()
        
    except Exception as e: 
        print(f"[{get_time()}] ERROR SELL {symbol}: {e}")
        if "precision" in str(e).lower() or "amount" in str(e).lower():
            print(f"[{get_time()}] ⚠️ Ошибка лимитов Bybit. Принудительно чищу память от {symbol}.")
            if symbol in positions: del positions[symbol]
            save_positions()

# ==========================================
# 🔁 ГЛАВНЫЙ ЦИКЛ СОПРОВОЖДЕНИЯ
# ==========================================
def trade():
    global positions, last_heartbeat, start_of_day_usdt, daily_profit_usdt
    
    # Вычисляем % прибыли за сегодня
    current_profit_pct = (daily_profit_usdt / start_of_day_usdt) * 100 if start_of_day_usdt > 0 else 0
    
    # Оптимизация: спрашиваем Bybit про тренд BTC, ТОЛЬКО если профит уже пробил 2.3%
    target_reached_in_sideways = False
    if current_profit_pct >= DAILY_PROFIT_TARGET_PCT:
        target_reached_in_sideways = not btc_ok()
    
    for sym in list(positions.keys()):
        df = fetch(sym, TIMEFRAME)
        if df is None: continue
        row = df.iloc[-1]
        price = row['close']
        pos = positions[sym]
        
        adx_val = row['adx']
        is_trend = adx_val > TREND_ADX
        
        current_logic = 'SNIPER'
        if STRATEGY_MODE == 1: current_logic = 'SURFER'
        elif STRATEGY_MODE == 2: current_logic = 'HYBRID'
        elif STRATEGY_MODE == 3: current_logic = 'SURFER' if is_trend else 'SNIPER'
        elif STRATEGY_MODE == 4: current_logic = 'HYBRID' if is_trend else 'SNIPER'
        
        if current_logic == 'SURFER':
            activation = pos['entry'] + (pos['tp'] - pos['entry']) * 0.8
            if price >= activation and not pos.get('trailing_active'):
                positions[sym]['trailing_active'] = True
                print(f"[{get_time()}] 🏄 {sym}: Активирован СЕРФЕР (Трейлинг-стоп).")
                send_tg(f"🏄 <b>{sym}</b>: Ракета! Трейлинг активирован.")
                
            if pos.get('trailing_active'):
                dyn_sl = price * TRAILING_STEP
                if dyn_sl > pos['sl']: positions[sym]['sl'] = dyn_sl
                
            if price <= pos['sl']: sell(sym, price, "SL/Trailing")
                
        elif current_logic == 'HYBRID':
            if price >= pos['tp'] and not pos.get('half_sold'):
                sell(sym, price, "TP (Фиксация 50%)", portion=0.5)
                positions[sym]['trailing_active'] = True
                positions[sym]['sl'] = pos['entry'] * 1.0025 
                
            if pos.get('trailing_active'):
                dyn_sl = price * TRAILING_STEP
                if dyn_sl > pos['sl']: positions[sym]['sl'] = dyn_sl
                
            if price <= pos['sl']: sell(sym, price, "SL/Trailing (Остаток)")
            
        elif current_logic == 'SNIPER':
            # БЛОК ФИКСАЦИИ: Если норма выполнена и Биткоин не в тренде
            if target_reached_in_sideways:
                sell(sym, price, f"TP (Таргет {DAILY_PROFIT_TARGET_PCT}%. Боковик)")
                continue

            # ЧИСТАЯ МАТЕМАТИКА: без суеты и лишних движений стопа
            if price <= pos['sl']: 
                sell(sym, price, "SL (Полный стоп)")
            elif price >= pos['tp']: 
                sell(sym, price, "TP (Полный тейк)")
            
        save_positions()

    if time.time() - last_heartbeat >= 60:
        mode_names = {1:"Серфер", 2:"Гибрид", 3:"Умный Серфер", 4:"Умный Гибрид"}
        btc_status = "🟢" if btc_ok() else "🔴"
        print(f"[{get_time()}] 📡 ПУЛЬС: BTC {btc_status} | Режим: {mode_names[STRATEGY_MODE]} | Позиций: {len(positions)}/{MAX_POSITIONS}")
        last_heartbeat = time.time()

    # if len(positions) >= MAX_POSITIONS or not btc_ok(): return
    if len(positions) >= MAX_POSITIONS: return

    # Блокируем сканирование новых позиций, если таргет во флэте выполнен
    if target_reached_in_sideways: return

    for sym in SYMBOLS:
        if sym in positions: continue
        df = fetch(sym, TIMEFRAME)
        if df is None: continue
        
        row = df.iloc[-1]; prev = df.iloc[-2]
        
        adx_val = row['adx']
        rsi_val = row.get('rsi', 50)
        
        # 1. Защита от "Мертвой зоны" (Полный штиль)
        if adx_val < 25:
            continue
            
        # 2. Базовые условия пробоя и тренда
        is_macro_bullish = prev['ema50'] > prev['ema200']
        is_breakout = row['close'] > prev['resistance']
        # has_volume = row['vol'] > prev['vol_ma'] * 1.2
        has_volume = row['vol'] > prev['vol_ma']
        
        if not (is_macro_bullish and is_breakout and has_volume):
            continue
            
        # 3. Фильтры режимов (Вход)
        is_trend = adx_val > TREND_ADX
        
        if not is_trend:
            # ДЛЯ СНАЙПЕРА (Боковик: 20 <= ADX <= 30)
            if rsi_val > 55:
                continue 
        else:
            # ДЛЯ ГИБРИДА (Тренд: ADX > 30) - Игнорируем RSI
            if rsi_val > 75:
                continue 
        
        entry = row['close']
        atr = row['atr']
        sl = entry - atr * SL_MULT
        tp = entry + atr * TP_MULT
        
        if entry > sl:
            buy(sym, entry, sl, tp)
            break

def check_daily_summary():
    global daily_profit_usdt, daily_fees_usdt, daily_trades, daily_wins, daily_losses, last_summary_date
    now = datetime.now(TZ_EKAT)
    current_date = now.strftime('%Y-%m-%d')
    
    # Бронебойная проверка времени: отправляем в 05:05 утра по Екатеринбургу (сразу после закрытия суток на Bybit)
    is_time_to_send = now.hour > 5 or (now.hour == 5 and now.minute >= 5)
    
    if is_time_to_send and last_summary_date != current_date:
        wr = (daily_wins / daily_trades * 100) if daily_trades > 0 else 0
        
        # Обновленный текст для Telegram
        tg_msg = (
            f"📊 <b>Z-ОТЧЕТ</b>\n"
            f"Сделок: {daily_trades}\n"
            f"Winrate: {round(wr,1)}%\n"
            f"Комиссии биржи: -{round(daily_fees_usdt, 2)} USDT\n" # <--- Добавили
            f"<b>ЧИСТЫЙ ПРОФИТ: {round(daily_profit_usdt, 2)} USDT</b>"
        )
        send_tg(tg_msg, parse_mode="HTML")
        
        # Сброс всех переменных на новые сутки
        daily_profit_usdt = 0; daily_fees_usdt = 0; daily_trades = 0  # <--- Сброс комиссии
        daily_wins = 0; daily_losses = 0
        last_summary_date = current_date
        
        # Фиксируем баланс на начало новых суток
        try:
            balance_info = exchange.fetch_balance()
            start_of_day_usdt = balance_info['USDT']['total']
        except Exception as e:
            print(f"[{get_time()}] Ошибка обновления баланса: {e}")

if __name__ == "__main__":
    mode_names = {1:"Серфер", 2:"Гибрид", 3:"Умный Серфер", 4:"Умный Гибрид"}
    print(f"[{get_time()}] 🚀 BOT V6.2 ELITE ЗАПУЩЕН")
    
    # Получаем стартовый баланс при запуске скрипта
    try:
        balance_info = exchange.fetch_balance()
        start_of_day_usdt = balance_info['USDT']['total']
    except:
        start_of_day_usdt = 0.0
        
    print(f"[{get_time()}] 🧠 Активный режим: {mode_names[STRATEGY_MODE]}")
    load_positions()
    while True:
        try:
            update_dynamic_symbols()
            trade()
            check_daily_summary()
        except Exception as e: 
            print(f"[{get_time()}] MAIN ERROR: {e}")
            time.sleep(30)
        time.sleep(15)
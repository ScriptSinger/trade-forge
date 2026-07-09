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
pending_signals = {}   # НОВОЕ: очередь сигналов, ожидающих отката (используется только если USE_PULLBACK_ENTRY = True)
last_heartbeat = 0

start_of_day_usdt = 0.0
daily_profit_usdt = 0.0
daily_fees_usdt = 0.0
daily_trades = 0
daily_wins = 0
daily_losses = 0
last_summary_date = None

daily_limit_hit = False   # НОВОЕ: единый флаг блокировки новых входов (по прибыли ИЛИ по убытку)

STATS_FILE = 'daily_stats.json'

def save_stats():
    """Сохраняет текущий Z-отчет на диск."""
    data = {
        'date': datetime.now(TZ_EKAT).strftime('%Y-%m-%d'),
        'daily_profit_usdt': daily_profit_usdt,
        'daily_fees_usdt': daily_fees_usdt,
        'daily_trades': daily_trades,
        'daily_wins': daily_wins,
        'daily_losses': daily_losses
    }
    with open(STATS_FILE, 'w') as f:
        json.dump(data, f)

def load_stats():
    """Восстанавливает Z-отчет из файла при запуске."""
    global daily_profit_usdt, daily_fees_usdt, daily_trades, daily_wins, daily_losses, last_summary_date
    if os.path.exists(STATS_FILE):
        try:
            with open(STATS_FILE, 'r') as f:
                data = json.load(f)
                if data.get('date') == datetime.now(TZ_EKAT).strftime('%Y-%m-%d'):
                    daily_profit_usdt = data.get('daily_profit_usdt', 0.0)
                    daily_fees_usdt = data.get('daily_fees_usdt', 0.0)
                    daily_trades = data.get('daily_trades', 0)
                    daily_wins = data.get('daily_wins', 0)
                    daily_losses = data.get('daily_losses', 0)
                    last_summary_date = data.get('date')
                    print(f"[{get_time()}] 💾 Статистика восстановлена: {round(daily_profit_usdt, 2)} USDT")
        except: pass

# --- ПЕРЕМЕННЫЕ ДОЛГОСРОЧНОЙ ПАМЯТИ ---
DAILY_MEMORY_FILE = 'daily_memory.json'

def get_time():
    return datetime.now(TZ_EKAT).strftime('%H:%M:%S')

# --- ФУНКЦИИ ДОЛГОСРОЧНОЙ ПАМЯТИ ---
def save_daily_memory(balance):
    """Сохраняет утренний баланс и текущую дату в файл."""
    data = {
        'date': datetime.now(TZ_EKAT).strftime('%Y-%m-%d'),
        'start_balance': balance
    }
    try:
        with open(DAILY_MEMORY_FILE, 'w') as f:
            json.dump(data, f)
        print(f"[{get_time()}] 💾 Баланс {balance} USDT жестко зафиксирован в памяти на сегодня.")
    except Exception as e:
        print(f"[{get_time()}] ⚠️ Ошибка записи памяти: {e}")

def load_daily_memory():
    """Загружает баланс из файла, если дата совпадает с сегодняшней."""
    if os.path.exists(DAILY_MEMORY_FILE):
        try:
            with open(DAILY_MEMORY_FILE, 'r') as f:
                data = json.load(f)
                if data.get('date') == datetime.now(TZ_EKAT).strftime('%Y-%m-%d'):
                    return data.get('start_balance')
        except Exception as e:
            print(f"[{get_time()}] ⚠️ Ошибка чтения памяти: {e}")
    return None

def get_real_total_balance():
    """Считает баланс: USDT + стоимость всех позиций по рынку."""
    try:
        balance_info = exchange.fetch_balance()
        total_val = balance_info['free'].get('USDT', 0)

        for sym, pos in positions.items():
            ticker = exchange.fetch_ticker(sym)
            total_val += pos['qty'] * ticker['last']

        return total_val
    except Exception as e:
        print(f"[{get_time()}] ⚠️ Ошибка баланса: {e}")
        return 0.0

# -----------------------------------

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
        markets = exchange.load_markets()
        tickers = exchange.fetch_tickers()

        candidates = []

        for symbol, data in markets.items():
            if symbol.endswith('/USDT') and data['active'] and not any(s in symbol for s in ['USDC', 'USD1', 'FDUSD', 'TUSD', 'BUSD', 'DAI', 'USDE', 'EUR', 'AEUR']):
                ticker = tickers.get(symbol)
                if not ticker: continue

                vol_24h = ticker.get('quoteVolume', 0)
                if vol_24h > 3_000_000:
                    high = ticker.get('high', 0)
                    low = ticker.get('low', 0)
                    if low > 0:
                        volatility = ((high - low) / low) * 100
                        candidates.append((symbol, volatility))

        candidates.sort(key=lambda x: x[1], reverse=True)
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

        target_qty = actual_qty if portion >= 1.0 else (pos['qty'] * portion)

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
        save_stats()

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
    global positions, pending_signals, last_heartbeat, start_of_day_usdt, daily_profit_usdt, daily_limit_hit

    # 1. Считаем PnL от реального баланса (с учетом открытых позиций)
    current_balance = get_real_total_balance()
    if current_balance <= 0: return

    current_profit_usdt = current_balance - start_of_day_usdt
    current_profit_pct = (current_profit_usdt / start_of_day_usdt) * 100 if start_of_day_usdt > 0 else 0

    is_btc_ok = btc_ok()  # True = BTC выше EMA200 (тренд), False = BTC ниже EMA200 (флэт/медвежка)

    # 2. Целевая планка профита зависит от текущего режима BTC.
    #    Лимиты работают ВСЕГДА — is_btc_ok здесь НЕ решает, включать ли лимит,
    #    а только какую планку профита применять.
    active_target = DAILY_PROFIT_TARGET_TREND_PCT if is_btc_ok else DAILY_PROFIT_TARGET_FLAT_PCT

    profit_limit_hit = current_profit_pct >= active_target
    loss_limit_hit = current_profit_pct <= -DAILY_LOSS_LIMIT_PCT
    block_new_entries = profit_limit_hit or loss_limit_hit

    if block_new_entries and not daily_limit_hit:
        daily_limit_hit = True
        if profit_limit_hit:
            reason_txt = f"прибыль {round(current_profit_pct,2)}% ≥ цели {active_target}%"
        else:
            reason_txt = f"убыток {round(current_profit_pct,2)}% ≥ лимита {DAILY_LOSS_LIMIT_PCT}%"
        print(f"[{get_time()}] 🛑 ЛИМИТ ДОСТИГНУТ ({reason_txt}). Новые входы заблокированы до конца суток.")
        send_tg(f"🛑 <b>Лимит дня достигнут</b>: {reason_txt}.\nНовые входы заблокированы до конца суток.", parse_mode="HTML")
    elif not block_new_entries:
        daily_limit_hit = False

    # 3. Цикл сопровождения позиций
    for sym in list(positions.keys()):
        df = fetch(sym, TIMEFRAME)
        if df is None: continue
        row = df.iloc[-1]
        price = row['close']
        pos = positions[sym]

        # Принудительное закрытие ТОЛЬКО если прибыль уже достигла цели И BTC ушёл во флэт/медвежку.
        # Это отдельная защита прибыли при развороте — НЕ используется для блокировки новых входов.
        if profit_limit_hit and not is_btc_ok:
            sell(sym, price, f"TP (Лимит {active_target}%, BTC ниже EMA200)")
            continue

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
            if price <= pos['sl']:
                sell(sym, price, "SL (Полный стоп)")
            elif price >= pos['tp']:
                sell(sym, price, "TP (Полный тейк)")

        save_positions()

    if time.time() - last_heartbeat >= 60:
        mode_names = {1:"Серфер", 2:"Гибрид", 3:"Умный Серфер", 4:"Умный Гибрид"}
        btc_status = "🟢" if is_btc_ok else "🔴"
        limit_status = "🛑" if block_new_entries else "✅"

        print(f"[{get_time()}] 📡 ПУЛЬС: BTC {btc_status} | PNL: {round(current_profit_pct, 2)}% | Цель: {active_target}% | Лимит: {limit_status}")
        print(f"[{get_time()}] 🧠 Режим: {mode_names[STRATEGY_MODE]} | Вход: {'Пуллбэк' if USE_PULLBACK_ENTRY else 'Пробой'} | Позиций: {len(positions)}/{MAX_POSITIONS}")
        last_heartbeat = time.time()

    if len(positions) >= MAX_POSITIONS: return

    # Блокируем сканирование новых позиций, если дневной лимит (прибыль или убыток) достигнут
    if block_new_entries: return

    for sym in SYMBOLS:
        if sym in positions: continue

        df = fetch(sym, TIMEFRAME)
        if df is None: continue

        row = df.iloc[-1]; prev = df.iloc[-2]

        # --- Если пуллбэк-режим включён и по этому символу уже есть сигнал в ожидании отката ---
        if USE_PULLBACK_ENTRY and sym in pending_signals:
            sig = pending_signals[sym]
            sig['bars_left'] -= 1
            target_entry = sig['breakout_price'] * PULLBACK_ENTRY_PCT
            if row['close'] <= target_entry and row['close'] > sig['sl']:
                entry = row['close']
                if entry > sig['sl']:
                    buy(sym, entry, sig['sl'], sig['tp'])
                    del pending_signals[sym]
                    break
            elif sig['bars_left'] <= 0:
                del pending_signals[sym]  # сигнал сгорел, отката не было
            continue

        adx_val = row['adx']
        rsi_val = row.get('rsi', 50)

        # 1. Защита от "Мертвой зоны" (Полный штиль)
        if adx_val < 25:
            continue

        # 2. Базовые условия пробоя и тренда
        is_macro_bullish = prev['ema50'] > prev['ema200']
        is_breakout = row['close'] > prev['resistance']
        has_volume = row['vol'] > prev['vol_ma']

        if not (is_macro_bullish and is_breakout and has_volume):
            continue

        # 3. Фильтры режимов (Вход)
        is_trend = adx_val > TREND_ADX

        if not is_trend:
            if rsi_val > 55:
                continue
        else:
            if rsi_val > 75:
                continue

        entry_signal = row['close']
        atr = row['atr']
        sl = entry_signal - atr * SL_MULT
        tp = entry_signal + atr * TP_MULT

        if entry_signal <= sl:
            continue

        if USE_PULLBACK_ENTRY:
            # Не входим сразу — ставим сигнал в очередь ожидания отката
            pending_signals[sym] = {
                'breakout_price': entry_signal,
                'sl': sl, 'tp': tp,
                'bars_left': PULLBACK_TIMEOUT_BARS
            }
        else:
            # Вход сразу на пробое (проверенное поведение)
            buy(sym, entry_signal, sl, tp)
            break

def check_daily_summary():
    global daily_profit_usdt, daily_fees_usdt, daily_trades, daily_wins, daily_losses, last_summary_date, start_of_day_usdt, daily_limit_hit
    now = datetime.now(TZ_EKAT)
    current_date = now.strftime('%Y-%m-%d')

    is_time_to_send = now.hour > 5 or (now.hour == 5 and now.minute >= 5)

    if is_time_to_send and last_summary_date != current_date:
        wr = (daily_wins / daily_trades * 100) if daily_trades > 0 else 0

        tg_msg = (
            f"📊 <b>Z-ОТЧЕТ</b>\n"
            f"Сделок: {daily_trades}\n"
            f"Winrate: {round(wr,1)}%\n"
            f"Комиссии биржи: -{round(daily_fees_usdt, 2)} USDT\n"
            f"<b>ЧИСТЫЙ ПРОФИТ: {round(daily_profit_usdt, 2)} USDT</b>"
        )
        send_tg(tg_msg, parse_mode="HTML")

        daily_profit_usdt = 0; daily_fees_usdt = 0; daily_trades = 0
        daily_wins = 0; daily_losses = 0
        last_summary_date = current_date
        daily_limit_hit = False
        save_stats()

        try:
            start_of_day_usdt = get_real_total_balance()
            save_daily_memory(start_of_day_usdt)
        except Exception as e:
            print(f"[{get_time()}] Ошибка обновления баланса: {e}")

def send_startup_report():
    """Отправляет текущую статистику (X-отчет) при перезапуске бота, ничего не обнуляя."""
    global daily_profit_usdt, daily_fees_usdt, daily_trades, daily_wins

    wr = (daily_wins / daily_trades * 100) if daily_trades > 0 else 0

    tg_msg = (
        f"🔄 <b>БОТ ПЕРЕЗАПУЩЕН (X-ОТЧЕТ)</b>\n"
        f"📊 <b>Восстановленная статистика за день:</b>\n"
        f"Сделок: {daily_trades}\n"
        f"Winrate: {round(wr,1)}%\n"
        f"Комиссии биржи: -{round(daily_fees_usdt, 2)} USDT\n"
        f"<b>ЧИСТЫЙ ПРОФИТ: {round(daily_profit_usdt, 2)} USDT</b>"
    )
    send_tg(tg_msg, parse_mode="HTML")
    print(f"[{get_time()}] 🔄 Отправлен предварительный X-отчет при старте.")

if __name__ == "__main__":
    mode_names = {1:"Серфер", 2:"Гибрид", 3:"Умный Серфер", 4:"Умный Гибрид"}
    print(f"[{get_time()}] 🚀 BOT V6.3 ELITE ЗАПУЩЕН")

    saved_balance = load_daily_memory()
    if saved_balance is not None:
        start_of_day_usdt = saved_balance
        print(f"[{get_time()}] 🧠 Бот вспомнил утренний баланс: {start_of_day_usdt} USDT")
    else:
        try:
            start_of_day_usdt = get_real_total_balance()
            save_daily_memory(start_of_day_usdt)
        except:
            start_of_day_usdt = 0.0

    print(f"[{get_time()}] 🧠 Активный режим: {mode_names[STRATEGY_MODE]} | Вход: {'Пуллбэк' if USE_PULLBACK_ENTRY else 'Пробой'}")
    print(f"[{get_time()}] 🎯 Цели: Флэт {DAILY_PROFIT_TARGET_FLAT_PCT}% | Тренд {DAILY_PROFIT_TARGET_TREND_PCT}% | Стоп по убытку {DAILY_LOSS_LIMIT_PCT}%")
    load_positions()
    load_stats()

    send_startup_report()

    while True:
        try:
            update_dynamic_symbols()
            trade()
            check_daily_summary()
        except Exception as e:
            print(f"[{get_time()}] MAIN ERROR: {e}")
            time.sleep(30)
        time.sleep(15)

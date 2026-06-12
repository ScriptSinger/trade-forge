import telebot
from telebot import types
import os
import subprocess
import time
import threading

# --- НАСТРОЙКИ ---
TOKEN = ''
CHAT_ID = ''

bot = telebot.TeleBot(TOKEN)

# Глобальные переменные для стрима логов
stream_active = False
stream_file = ""

def is_owner(message):
    """Проверка, что командует именно хозяин"""
    return str(message.chat.id) == str(CHAT_ID)

def get_menu():
    """Создание клавиатуры с кнопками"""
    markup = types.ReplyKeyboardMarkup(resize_keyboard=True, row_width=2)
    markup.add(
        types.KeyboardButton("🟢 Запуск Pro Bot"), types.KeyboardButton("📄 Логи Pro (100)"),
        types.KeyboardButton("▶️ Стрим Pro (ВКЛ)"), types.KeyboardButton("⏸ Остановить стрим"),
        types.KeyboardButton("📊 Статус сервера"), types.KeyboardButton("🛑 УБИТЬ ВСЕХ БОТОВ")
    )
    return markup

@bot.message_handler(commands=['start'])
def start_cmd(message):
    if not is_owner(message): return
    bot.send_message(message.chat.id, "🎛 Добро пожаловать в Пульт Управления Сервером!", reply_markup=get_menu())

@bot.message_handler(func=lambda message: is_owner(message))
def handle_buttons(message):
    global stream_active, stream_file
    text = message.text

    if text == "🟢 Запуск Pro Bot":
        # Указан точный путь /root/azamat и новое уникальное имя файла aza_trade.py
        os.system("screen -dmS azabot bash -c 'cd /root/azamat && stdbuf -oL python3 -u aza_trade.py | tee -a pro_session.log'")
        bot.send_message(CHAT_ID, "🚀 Бот (aza_trade) запущен в фоне (папка /root/azamat).")

    elif text == "🛑 УБИТЬ ВСЕХ БОТОВ":
        stream_active = False
        # Убиваем строго уникальный процесс aza_trade.py
        os.system("pkill -f aza_trade.py")
        # Закрываем уникальный экран azabot
        os.system("screen -S azabot -X quit")
        bot.send_message(CHAT_ID, "☠️ Бот (aza_trade) остановлен. Менеджер продолжает работу.")

    elif text == "📊 Статус сервера":
        try:
            screens = subprocess.check_output(['screen', '-ls']).decode('utf-8')
        except subprocess.CalledProcessError:
            screens = "Нет активных экранов (экраны чисты)."
        bot.send_message(CHAT_ID, f"🖥 **Активные фоновые экраны:**\n<pre>{screens}</pre>", parse_mode="HTML")

    elif text == "📄 Логи Pro (100)":
        send_logs('/root/azamat/pro_session.log')

    elif text == "▶️ Стрим Pro (ВКЛ)":
        if not stream_active:
            stream_active = True
            stream_file = '/root/azamat/pro_session.log'
            bot.send_message(CHAT_ID, "📡 Стрим логов ВКЛЮЧЕН.")
            threading.Thread(target=log_streamer, daemon=True).start()
        else:
            bot.send_message(CHAT_ID, "⚠️ Стрим уже работает! Сначала остановите текущий.")

    elif text == "⏸ Остановить стрим":
        stream_active = False
        bot.send_message(CHAT_ID, "🔇 Стрим логов остановлен.")

def send_logs(filename):
    """Функция отправки последних 100 строк"""
    try:
        if not os.path.exists(filename):
            bot.send_message(CHAT_ID, f"❌ Файл {filename} еще не создан.")
            return
        output = subprocess.check_output(['tail', '-n', '100', filename]).decode('utf-8')
        if len(output) > 3800:
            output = "...[часть скрыта]...\n" + output[-3800:]
        bot.send_message(CHAT_ID, f"📄 **Логи {filename}:**\n<pre>{output}</pre>", parse_mode="HTML")
    except Exception as e:
        bot.send_message(CHAT_ID, f"Ошибка: {e}")

def log_streamer():
    """Фоновый поток для стрима логов (читает только НОВЫЕ строки)"""
    global stream_active
    try:
        f = open(stream_file, 'r', encoding='utf-8')
        f.seek(0, os.SEEK_END)
        
        while stream_active:
            new_lines = f.readlines()
            if new_lines:
                text = "".join(new_lines)
                if len(text) > 3800: text = text[-3800:]
                bot.send_message(CHAT_ID, f"<pre>{text}</pre>", parse_mode="HTML")
            time.sleep(10) 
            
        f.close()
    except Exception as e:
        bot.send_message(CHAT_ID, f"Стрим прерван из-за ошибки: {e}")
        stream_active = False

if __name__ == "__main__":
    print("🎛 Менеджер запущен. Жду команд в Telegram...")
    bot.infinity_polling()
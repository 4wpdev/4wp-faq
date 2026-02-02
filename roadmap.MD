Post / Page / CPT
  └─ core Accordion
       └─ wrapped by forwp/faq
            ├─ Schema JSON (frontend)
            └─ Parsed on scan
                 └─ Aggregated
                      └─ CPT FAQ

===

1. Що робить користувач (адмін)

Створює будь-який запис:

post

page

будь-який CPT

Додає core Accordion

Натискає: “Convert to FAQ (4WP)”

фактично: обгортка + прапорець це FAQ

Все. Більше нічого не думає.

2. Що таке 4wp-faq насправді

НЕ окремий редактор
НЕ KB на цьому етапі

👉 Це FAQ-контекст над акордіоном + schema JSON

Технічно:

wrapper block forwp/faq

всередині тільки accordion

accordion item = FAQ item

3. Schema (коротко і чітко)

Генерується тільки якщо є wrapper

Збирається з accordion items

1 JSON-LD на сторінку

Вивід:

add_action( 'wp_footer', '4wp_faq_schema', 99 );


Кінець.

4. Аналіз усіх записів (ключовий момент)

На цьому етапі ніяких кастомних таблиць і магії.

Що ми робимо:

Беремо ВСІ записи:

posts

pages

CPT

Шукаємо в post_content:

<!-- wp:forwp/faq -->

👉 Це і є факт існування FAQ у записі

5. Що таке FAQ item у цьому контексті

FAQ item = accordion item усередині forwp/faq

Має:

question

answer

post_id (де використовується)

post_type

permalink

title поста

Все. Ніяких “entity”.

6. Формування CPT FAQ (вже після)

Ось тут зʼявляється CPT FAQ, але:

❗ НЕ як джерело правди
❗ як агрегований результат

Як формується:

скануємо всі записи з forwp/faq

дістаємо всі FAQ items

групуємо по question

створюємо / оновлюємо:

faq CPT

meta:

answers

used_in_posts[]

used_in_post_types[]

count_usage

7. Що дає CPT FAQ

Тепер ми можемо:

мати базу запитань

бачити:

де використовується

на яких сторінках

будувати:

FAQ index

тематичні сторінки

internal linking

SEO-хаб

8. Дуже коротко вся схема
Post / Page / CPT
  └─ core Accordion
       └─ wrapped by forwp/faq
            ├─ Schema JSON (frontend)
            └─ Parsed on scan
                 └─ Aggregated
                      └─ CPT FAQ

9. Важливе (і фінал)

Джерело правди → контент

CPT FAQ → похідна

Schema → побічний ефект

        
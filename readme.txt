=== Fabio Chatbot Lite for Shop, Blog & Forums ===
Contributors: fabioplugins
Tags: ai chatbot, chatgpt, wordpress chatbot, site search, ai search, anthropic, gemini, mistral, woocommerce
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fabio AI ChatBot Lite adds an AI chatbot that answers visitors' questions strictly based on your website's real content and products.

== Description ==

Most websites lose 80% of their visitors because they don't find what they are looking for within 5 seconds. Fabio AI ChatBot Lite adds an AI chatbot that answers visitors' questions and drives them to the right content.

https://youtu.be/dl5Ef9bUrro

Fabio AI ChatBot Lite is a WordPress plugin that installs an AI-powered chatbot to help visitors quickly find accurate information on your website. Fabio AI ChatBot Lite answers exclusively from your WordPress, WooCommerce, and bbPress content. The chatbot never uses external knowledge or the public internet. Fabio Lite answers in your WordPress installation language, fitting nearly all languages on earth. Icing on the cake : the analytics features.

You choose which AI provider generates the answers: OpenAI, Google Gemini, Mistral AI, or Anthropic (Claude). Bring your own API key from any of these providers. The cost with entry-level models is about 1 USD for 300 conversations (questions and answers).

Video tutorials ("how to" guides) are available on the official [YouTube channel](https://www.youtube.com/@FabioPlugins)
A complete feature comparison list is available [here](https://fabio-plugins.com/pricing/)
A live demo site is available at [getfreeplugins.com](https://getfreeplugins.com)

Key principles:

* ✅ Answers based only on scanned website content
* 🌍 Answers in your WordPress installation language
* 🔄 Manual or automatic site scanning
* 💬 Unlimited conversations
* ⬇️ Unlimited conversation download

== Features ==
* 📄 **Full scan of WordPress content**
* Blog: scans pages, posts, tags, and categories, check the [set-up guide](https://fabio-plugins.com/installation-guide/#step-01)
* Blog: cross-references information across multiple articles, categories, and tags to deliver comprehensive, summarized answers — even across 500+ pages
* 🛒 **WooCommerce integration**
* WooCommerce: scans products, prices, discounts, reviews, and descriptions
* WooCommerce: finds the cheapest or most expensive product among more than 1000 products, and identifies the ones matching specific features requested by the user
* 💬 **bbPress forum support**: scans forum topics and replies to provide the most relevant answer to support questions
* 🗨️ **Chatbot on your forum**: choose how the chatbot appears on bbPress pages (same as the rest of the site, automatic insertion at the top of forum pages, or floating bubble)
* 🧩 **Custom fields**: add the text of your custom fields (ACF, Meta Box, Pods, Toolset, JetEngine) and of your own content types to what the chatbot reads
* Manual scan or automatic daily scan
* Front-end chatbot via shortcode, pop-up bubble, or automatic insertion, check the [set-up guide](https://fabio-plugins.com/installation-guide/#step-06)
* Customizable welcome message to increase user engagement, check the [set-up guide](https://fabio-plugins.com/installation-guide/#step-07)
* 🎨 **Customizable colors, gradients, and bubble position for a chatbot that blends into your design**, check the [set-up guide](https://fabio-plugins.com/installation-guide/#step-05)
* 🔒 **Secure and reliable answer generation**
* 🤖 **Support for multiple AI providers**: OpenAI, Google Gemini, Mistral AI, and Anthropic (Claude) — bring your own API key, check the [set-up guide](https://fabio-plugins.com/installation-guide/#step-04)
* OpenAI models: GPT-5.6 Luna, GPT-6 Luna
* Google Gemini models: Gemini 3.1 Flash-Lite, Gemini 3.8 Flash
* Mistral AI models: Mistral Large 3
* Anthropic models: Claude Haiku 5.5, Claude Sonnet 5.5
* Compatible with large, small, and medium-sized AI language models, so you can balance answer quality against API cost depending on your traffic volume
* 📊 **Built-in analytics** for the last 24 hours, filterable by keyword:
* Conversations: one line per visitor conversation, with the first page, the intent, and which links shown by the chatbot were clicked
* Visitor intent: see what visitors want (purchase, price, delivery, support, comparison, contact, complaint, information) and filter the conversations by intent
* Word cloud: the words visitors type the most, click a word to filter the page
* Optional AI analysis of the intent of older conversations, using your own API key
* Supports any language available on your WordPress installation
* Unlimited CSV export of conversation history
* 💡 **Helpful alternatives**: when your site has no answer, the chatbot suggests up to 3 related contents from your site instead of a dead end
* 🧠 Conversation memory: the chatbot understands short follow-up answers such as "yes" or "the first one" by using the last messages of the conversation
* 🗂️ Conversation kept while visitors browse your site: the chat history survives page changes, and the chatbot opens closed on every new page. A visitor can start in the chatbot inserted in your content and continue the same conversation in the floating bubble on your forum (same browser tab)

== Usage ==

1. 🔌 Install and activate the plugin
2. 🔑 Configure your own API key in the settings (OpenAI, Gemini, Mistral, or Anthropic)
3. 🔍 A site scan runs automatically
4. 🖥️ Choose how to display the chatbot: shortcode `[fabioai_chatbot]`, floating bubble, or automatic insertion
5. 📊 Analyze the questions asked by users and the answers given by Fabio AI ChatBot Lite

== Settings ==

From the WordPress admin panel, you can:

* 🔍 Launch a manual site scan
* 💬 Define the chatbot welcome message
* 🔑 Configure the OpenAI, Gemini, Mistral, and/or Anthropic API key
* 🤖 Choose the specific AI model per provider (e.g. Claude Haiku 5.5 for lower cost, Claude Sonnet 5.5 for higher accuracy)
* 🎨 Customize the chatbot's appearance (colors, gradients, bubble position)
* ⬇️ Download conversations

== Security & Reliability ==

* 🔒 Responses are strictly limited to scanned site content
* 🚫 No document uploads
* 🧹 Automatic content cleaning and segmentation
* 🛡️ Protection against invented or out-of-scope answers

== External services ==

This plugin connects to third-party AI providers to generate chatbot responses based on your website's scanned content. You must provide your own API key for the provider you choose to use; no data is sent unless you configure a key.

-- OpenAI --
* 🎯 Usage: Generates chatbot responses when OpenAI is selected as the provider.
* 📤 Data sent: user questions (prompts), the last few messages of the visitor's current conversation, site content segments used as context, and your API key (for authentication). If you click "Analyze with AI" on the Analytics page, the text of past visitor questions (shortened) is also sent, to label their intent.
* 📄 [Terms of Use](https://openai.com/policies/row-terms-of-use/)
* 🔐 [Privacy Policy](https://openai.com/policies/row-privacy-policy/)

-- Google Gemini --
* 🎯 Usage: Generates chatbot responses when Gemini is selected as the provider.
* 📤 Data sent: user questions (prompts), the last few messages of the visitor's current conversation, site content segments used as context, and your API key (for authentication). If you click "Analyze with AI" on the Analytics page, the text of past visitor questions (shortened) is also sent, to label their intent.
* 📄 [Terms of Service](https://ai.google.dev/gemini-api/terms)
* 🔐 [Privacy Policy](https://policies.google.com/privacy)

-- Mistral AI --
* 🎯 Usage: Generates chatbot responses when Mistral is selected as the provider.
* 📤 Data sent: user questions (prompts), the last few messages of the visitor's current conversation, site content segments used as context, and your API key (for authentication). If you click "Analyze with AI" on the Analytics page, the text of past visitor questions (shortened) is also sent, to label their intent.
* 📄 [Terms of Service](https://legal.mistral.ai/terms/)
* 🔐 [Privacy Policy](https://legal.mistral.ai/terms/privacy-policy)

-- Anthropic --
* 🎯 Usage: Generates chatbot responses when Anthropic (Claude) is selected as the provider.
* 📤 Data sent: user questions (prompts), the last few messages of the visitor's current conversation, site content segments used as context, and your API key (for authentication). If you click "Analyze with AI" on the Analytics page, the text of past visitor questions (shortened) is also sent, to label their intent.
* 📄 [Terms of Service](https://www.anthropic.com/legal/commercial-terms)
* 🔐 [Privacy Policy](https://www.anthropic.com/legal/privacy)

API keys are stored locally in your WordPress database and are only used to authenticate requests to the provider you selected. You are responsible for complying with the terms and policies of the AI provider you choose to use.

== Privacy ==

The analytics features store the following data in your own WordPress database. Nothing is sent to Fabio-Plugins.com or to any server other than the AI provider you selected.

* Each visitor question, the chatbot answer, the date and time, the sources shown, and an intent label (for example "price" or "support").
* A random session identifier, generated in the visitor's browser and kept in the browser session storage only. It is not a cookie, it is deleted when the tab is closed, and it only links the questions of one visit together.
* The path of the page where the question was asked (without the query string).
* The clicks on the links shown by the chatbot (link address and time), linked to the conversation by the session identifier.

The plugin does not store IP addresses, names, email addresses or user accounts. Questions typed by visitors are free text: please inform your visitors, in your privacy policy, that their questions are processed by the AI provider you selected. You can download the conversations as a CSV file and delete them from the Analytics page. The intent label is returned by the AI provider together with the answer, without any additional request.

== Upgrade to Biz ==

Need more control over your chatbot? Fabio AI ChatBot Biz unlocks advanced features for growing sites:

* 🔄 **Incremental scan**: automatically scans any new or updated content, so your chatbot always answers with up-to-date information
* 🧩 **Automatic re-indexing of custom fields**: a page is re-indexed as soon as it or its fields change
* 💬 **Comments scanning**: choose whether the chatbot scans and reads your visitors' comments
* 🎯 **Buying-intent detection**: an additional instructions field to fine-tune how the chatbot detects and reacts to buying intent, guiding visitors toward a call-to-action
* 🎭 **2 extra tones**: Friendly, and Professional & Serious, in addition to the default tone
* 🖼️ **Custom bubble icon**: replace the default chat icon with your own logo for more virality and engagement
* 📅 **Extended analytics**: filter your analytics by the last 30 days, all time or a custom date range, and receive a daily report by email
* 🆓 **14-day free trial**

All your Lite settings, scanned content, and conversation history carry over automatically when you upgrade — nothing to reconfigure.

👉 [Compare plans and upgrade](https://fabio-plugins.com/pricing/)

== Installation ==

1. 📦 Upload the plugin folder to /wp-content/plugins/
2. ⚡ Activate the plugin from the Plugins menu
3. ⚙️ Configure the plugin from the admin settings page

**Important — after updating the plugin:** go to the plugin settings page and run a manual site scan, so your content is re-indexed with the latest improvements.

== FAQ ==

= 💰 What is the cost in tokens for each answer? =
With entry-level models such as GPT-5.6 Luna, the content sent to the AI model for each answer, before the visitor's question and the chatbot's answer are added, costs about 0.30 to 0.33 US cents (roughly 30 to 33 US cents per 100 answers).

= 🤖 Which AI model should I choose? =
The entry-level models from Gemini, OpenAI, Anthropic and Mistral are enough. They give an answer in less than 10 seconds, even when the answer requires cross-referencing information between 1,000 different pages.

= 📈 How many pages can the chatbot handle? =
We run our tests on a WooCommerce demo site with 1,000 products and on a how-to demo site with 500 pages. We see no difference in results between our tests on websites of 1,000 pages and on websites of fewer than 100 pages.

= 🧩 Can Fabio Chatbot Lite read custom fields? =
Yes. In step 1 of the settings page, add a row to "Custom fields to index": choose the content type, pick or type the field key and name it (the name is what the AI reads above the field's text). Fields created with ACF, Meta Box (default storage), Pods (default storage), Toolset and JetEngine (default storage) can be added, and content types created with Custom Post Type UI appear in the menu. Fields stored in a separate database table cannot be read. Once you choose a field for one of your own content types, that content type is scanned in full. Never map private fields: indexed text can appear in chatbot answers. Changes made to a field are picked up by the next automatic or manual scan. The paid version, Fabio ChatBot Biz, also scans your visitors' comments and re-indexes a page automatically as soon as it or its fields change.

= 📊 How do I analyze the conversations? =
Go to the Analytics page. It covers the last 24 hours. You will find the number of questions, the number of unique questions, the most frequent visitor intent, and a list of conversations (one line per visitor). Click a line to read the exchanges and see which links shown by the chatbot were clicked. You can filter by keyword or by intent, explore the word cloud, and download the conversations as a CSV file. The Biz version covers longer periods, up to the full history.

= 🎯 How is the visitor intent detected? =
The AI model gives an intent label (purchase, price, delivery, support, comparison, contact, complaint, information, or off-topic) together with each answer, at no extra cost. Conversations recorded before version 1.2.0 have no label: the Analytics page offers an "Analyze with AI" button that labels them in short batches with your own API key. This uses a small number of API requests that you pay to your AI provider.

= 🔒 Does the analytics use cookies or store personal data? =
No cookies and no personal data such as IP addresses, names or email addresses. A random identifier is kept in the browser session storage only, and is deleted when the tab is closed. See the Privacy section for the full list of stored data.

== Changelog ==

= 1.3.1 =
* Improved: faster answers. The chatbot no longer calls the AI provider to filter the question's stop words; it uses a built-in list for the site language (English, French, German, Italian, Spanish, Portuguese). Other languages keep the previous behavior.

= 1.3.0 =
* New: bbPress display setting in step 4 of the settings page (shown when bbPress is active). On forum pages, choose "Same as above", "Automatic insertion" (top of the forum home, each forum, topic and reply page) or "Floating bubble".
* New: the conversation now follows the visitor between the chatbot inserted in the content and the floating bubble, for example from a page to the forum, within the same browser tab.
* New: helpful alternatives. When your site does not contain the answer, the chatbot says so and suggests up to 3 related contents from your site.
* Improved: the links shown under an answer are displayed as a simple vertical list, without bullets.
* Fixed: the top of the inserted chatbot was cut off and stuck to its border after a page change.
* Note: a conversation in progress restarts once when the plugin is updated.
* Recommended: run a manual site scan after updating, so the helpful alternatives apply to your content.

= 1.2.0 =
* New: Conversations on the Analytics page. One line per visitor conversation, with the first page, the intent, and the links clicked. Click a line to read the exchanges and see which links were clicked.
* New: visitor intent. The chatbot labels each question (purchase, price, delivery, support, comparison, contact, complaint, information, off-topic), with a chart and a filter. Older conversations can be labeled with the "Analyze with AI" button.
* New: word cloud of the words visitors use the most. Click a word to filter the page.
* New: custom fields. In step 1 of the settings page, choose the custom fields (ACF, Meta Box, Pods, Toolset, JetEngine, theme or plugin fields) whose text the chatbot reads. The content types created with Custom Post Type UI and these plugins are listed in the menu, and a custom content type is scanned in full as soon as you choose a field for it.
* Improved: the search for answers now also covers the custom content types you have chosen.
* Changed: the Analytics page now shows the last 24 hours. The question list, the gap index and the question details are removed from the Lite version.
* Changed: the database is updated automatically on first load (new columns and a table for link clicks). The session identifier, the page path and the link clicks are described in the new Privacy section.
* Fixed: citation markers written with full-width brackets (for example 【S1】) no longer appear in answers.
* Removed: the dedicated support for directory-plugin listings. The custom fields feature replaces it for content stored in the standard WordPress custom fields.
* Recommended: run a manual site scan after updating.

= 1.0.9 =
* Updated the AI models in line with upcoming provider deprecations: GPT-6 Luna (OpenAI), Gemini 3.8 Flash (Google Gemini), Claude Haiku 5.5 and Claude Sonnet 5.5 (Anthropic). Mistral Large 3 is unchanged.
* Note: sites with a previously selected model that is no longer available will automatically fall back to the first available model for that provider.
* When the chatbot is opened (floating bubble or inline button), the conversation now scrolls to the beginning of the last answer.
* Questions about Anthropic (Claude) API keys are now matched to your guides like the other providers.
* Rewritten FAQ.
* Recommended: check your AI model in the plugin settings after updating.

= 1.0.8 =
* New: conversation memory. The chatbot now uses the last messages of the conversation to understand short follow-up answers such as "yes", "no" or "the first one".
* Short messages now reuse the visitor's previous question to find the right content on your site.
* The chatbot only asks for clarification when a question is still unclear after taking the conversation into account.
* The last messages of the conversation (shortened) are now sent to the selected AI provider, along with the question.
* Recommended: run a manual site scan after updating the plugin.

= 1.0.7 =
* The conversation history is now kept while visitors browse between pages (stored in the browser session only, cleared when the tab is closed).
* The chatbot is now displayed closed after a page change, in both inline and floating bubble modes, and the page no longer scrolls to the chatbot.
* Inline mode: a single button now opens and closes the conversation, with the same color as the send button.
* Fixed emoticons being turned into broken links in chatbot answers.
* Fixed leftover citation markers, empty brackets and URLs wrapped in brackets appearing in answers.
* Fixed chatbot errors when the browser storage is blocked.
* Analytics requests now require administrator permissions.
* Admin script is now refreshed automatically after each update.

= 1.0.6 =
* Fixed a bug where bbPress forums, topics, and replies were not indexed during the scan, even though the content existed and was published.

= 1.0.5 =
* Added a real-time visual progress bar for both automatic and manual scans, so you always know when a scan is running or complete.
* The first automatic scan now starts immediately upon activation, instead of waiting up to a minute.
* Added the next scheduled automatic scan date to the admin page.
* Added a detailed breakdown of indexed content by type (blog posts, pages, products, categories, tags, forums, topics, replies).
* WooCommerce product categories and product tags are now scanned and indexed as their own searchable content, in addition to being referenced inside each product.
* Displayed the minimum content length required for content to be indexed (50 characters for products, 200 characters for everything else).
* Reorganized the settings page into 5 clear numbered steps for easier setup.

= 1.0.4 =
* Added Shadow & Elevation
* UX improved on back end

= 1.0.3 =
* Added Anthropic (Claude) as a new supported AI provider, with Claude Haiku 4.5 and Claude Sonnet 5 models.
* Updated OpenAI model list: now offers GPT-5.6 Luna and GPT-5.4 Nano (previously offered older, retired models).
* Updated Google Gemini model list: now offers Gemini 3.1 Flash-Lite and Gemini 3.5 Flash (previous preview models have been retired by Google).
* Updated Mistral AI model list: now offers Mistral Large 3 only (previous models have been retired by Mistral).
* Note: sites with a previously selected model that is no longer available will automatically fall back to the first available model for that provider.
* Added links to video tutorials and the full feature list in the plugin description.

= 1.0.2 =
* Features description improved

= 1.0.1 =
* Adding an option to download all conversations without emptying the table.
* Removing "powered by".

= 1.0.0 =
* Initial submission of Fabio AI ChatBot Lite.

== Upgrade Notice ==

= 1.3.1 =
Faster answers for sites in English, French, German, Italian, Spanish or Portuguese.

= 1.3.0 =
New: choose how the chatbot appears on bbPress forum pages, keep one conversation between the inserted chatbot and the floating bubble, and get up to 3 helpful alternatives when the site has no answer. A conversation in progress restarts once after updating. After updating, run a manual site scan from the plugin settings page.

= 1.2.0 =
New Analytics: conversations with link clicks, visitor intent and word cloud, now limited to the last 24 hours. New: choose the custom fields the chatbot reads. The analytics store a random session identifier and the page path: see the new Privacy section. The dedicated support for directory-plugin listings has been removed. After updating, run a manual site scan.

= 1.0.9 =
AI models have been updated: GPT-6 Luna, Gemini 3.8 Flash, Claude Haiku 5.5 and Claude Sonnet 5.5. If your selected model is no longer available, the plugin automatically falls back to the first model of your provider. After updating, please check your AI model in the plugin settings.

= 1.0.8 =
The chatbot now remembers the last messages of the conversation to understand follow-up answers such as "yes". After updating, please run a manual site scan from the plugin settings page.

= 1.0.7 =
Conversation history is now kept between pages and the chatbot opens closed after a page change. Also fixes stray brackets and links in answers and strengthens analytics security.

= 1.0.3 =
Some AI models were retired by their providers and have been removed. If your selected model is no longer available, it now automatically falls back to another supported model. Anthropic (Claude) has been added as a new provider option.

== Screenshots ==

1. Analytics summary: questions, top visitor intent and conversations of the last 24 hours
2. Conversation and clicks details: the exchanges and the links the visitor clicked
3. Conversation analytics by visitor intent
4. Cloud of the keywords typed by visitors
5. How the site scan works
6. Bring your own API key (BYOK) and choose your AI provider
7. How to customize the chatbot
8. How to display the chatbot and add a welcome message

== More Information ==

* [Video tutorials](https://www.youtube.com/@FabioPlugins)
* [Live demo site](https://getfreeplugins.com)
* [Support Forum](https://fabio-plugins.com/support/)

== Author ==

Fabio-Plugins.com

<?php

class CategoryClassifier
{
    private static $rulesFile = null;

    /**
     * Default Fallback Rules
     */
    private static $defaultRules = [
        'artificial-intelligence' => [
            'name_ar' => 'ذكاء اصطناعي',
            'high_priority' => [
                'artificial intelligence', 'machine learning', 'deep learning', 'generative ai',
                'llm', 'slm', 'large language model', 'chatgpt', 'openai', 'claude', 'gemini',
                'deepseek', 'anthropic', 'mistral', 'groq', 'llama', 'copilot', 'midjourney',
                'stable diffusion', 'sora', 'runway', 'hugging face', 'huggingface', 'perplexity',
                'openrouter', 'sam altman', 'transformer', 'neural network', 'computer vision',
                'nlp', 'genai', 'prompt engineering', 'fine-tuning', 'rlhf', 'rag', 'vector database',
                'embedding', 'ai agent', 'autonomous agent', 'reasoning model', 'o1-preview',
                'o3-mini', 'synthetic data', 'ai lab', 'ai benchmark', 'ai alliance',
                'ذكاء اصطناعي', 'تعلم الآلة', 'تعلم عميق', 'ذكاء توليدي', 'نماذج لغوية', 'شات جي بي تي',
                'أوبن إيه آي', 'أوبن أي', 'كلود', 'جيميني', 'ديب سيك', 'أنثروبيك', 'لاما', 'روبوت محادثة',
                'شبكات عصبية', 'توليد الصور', 'توليد الفيديو', 'وكيل ذكي', 'وكلاء الذكاء', 'استدلال ذكي',
                'معالجة اللغات الطبيعية', 'رؤية حاسوبية', 'استرجاع معزز', 'تضمين المتجهات'
            ],
            'medium_priority' => [
                'model weights', 'inference', 'context window', 'tokens', 'hallucination',
                'multimodal', 'diffusion model', 'agi', 'asi', 'ai safety', 'alignment',
                'توليدي', 'هلوسة الذكاء', 'نافذة السياق', 'الذكاء العام', 'سلامة الذكاء'
            ]
        ],

        'cybersecurity' => [
            'name_ar' => 'أمن سيبراني',
            'high_priority' => [
                'cybersecurity', 'cyber security', 'cyberattack', 'ransomware', 'malware',
                'spyware', 'trojan', 'botnet', 'ddos', 'phishing', 'spear phishing', 'smishing',
                'zero-day', 'zero day', '0-day', 'cve-', 'exploit', 'vulnerability', 'backdoor',
                'data breach', 'data leak', 'credential stuffing', 'infostealer', 'c2 server',
                'lockbit', 'blackcat', 'darkside', 'threat actor', 'hacker', 'hackers',
                'penetration testing', 'red team', 'blue team', 'soc team', 'siem', 'edr',
                'xdr', 'firewall', 'zero trust', 'passkeys', 'identity theft', 'extortion',
                'أمن سيبراني', 'أمن المعلومات', 'قرصنة', 'مخترق', 'مخترقين', 'هكر', 'هكرز',
                'اختراق', 'ثغرة', 'ثغرة أمنية', 'ثغرات', 'برمجية خبيثة', 'برمجيات خبيثة', 'برامج الفدية',
                'فدية', 'تجسس', 'تسريب بيانات', 'تسريبات', 'هجوم سيبراني', 'هجمات سيبرانية',
                'حرمان من الخدمة', 'تصيد احتيالي', 'تصيد', 'حصان طروادة', 'هندسة اجتماعية',
                'جدار حماية', 'انعدام الثقة', 'تشفير طرفي', 'تسلل إلكتروني', 'حقن قواعد البيانات'
            ],
            'medium_priority' => [
                'security flaw', 'patch tuesday', 'bug bounty', 'cve', 'infosec', 'encryption',
                'decryption', '2fa', 'mfa', 'authenticator', 'adware', 'keylogger',
                'تحديث أمني', 'رقعة أمنية', 'تشفير', 'فك تشفير', 'مصادقة ثنائية', 'برامج إعلانية'
            ]
        ],

        'hardware-devices' => [
            'name_ar' => 'أجهزة وعتاد',
            'high_priority' => [
                'iphone', 'ipad', 'macbook', 'mac studio', 'imac', 'apple watch', 'vision pro',
                'airpods', 'galaxy s24', 'galaxy s25', 'galaxy s26', 'galaxy z fold', 'galaxy z flip',
                'pixel 9', 'pixel 10', 'pixel 11', 'pixel watch', 'snapdragon', 'exynos', 'geforce',
                'rtx 40', 'rtx 50', 'rtx 5070', 'rtx 5080', 'rtx 5090', 'blackwell', 'b200',
                'h100', 'h200', 'ryzen', 'radeon', 'arrow lake', 'lunar lake', 'core ultra',
                'tsmc', 'semiconductor', 'chipset', 'soc', 'nanometer', '3nm', '2nm', 'ddr5',
                'nvme ssd', 'motherboard', 'oled display', 'microled', 'amoled', 'smartwatch',
                'smart glasses', 'vr headset', 'ar glasses', 'foldable phone', 'foldables',
                'drone', 'drones', 'raspberry pi', 'gboard',
                'هاتف', 'هواتف', 'جوال', 'جوالات', 'آيفون', 'سامسونج جالاكسي', 'بكسل', 'ساعة ذكية',
                'سماعات لاسلكية', 'معالج', 'معالجات', 'كرت شاشة', 'بطاقة رسوميات', 'رقاقة', 'رقائق',
                'أشباه الموصلات', 'عتاد', 'عتاد صلب', 'شاشة أوليد', 'لوحة أم', 'شحن سريع',
                'هواتف قابلة للطي', 'نظارات واقع معزز', 'خوذة واقع افتراضي', 'حاسوب محمول', 'كاميرا احترافية',
                'طائرة مسيرة', 'طائرات مسيرة', 'درون'
            ],
            'medium_priority' => [
                'hardware', 'laptop', 'desktop pc', 'battery life', 'charging speed', 'camera sensor',
                'megapixels', 'refresh rate', '120hz', '144hz', 'gpu', 'cpu', 'ram', 'ssd',
                'chassis', 'earbuds', 'wearable', 'tablet',
                'حاسوب', 'لابتوب', 'بطارية', 'مستشعر', 'ميجابكسل', 'معدل تحديث', 'أجهزة ذكية', 'لوحي'
            ]
        ],

        'software-development' => [
            'name_ar' => 'برمجة وتطوير',
            'high_priority' => [
                'github', 'gitlab', 'bitbucket', 'pull request', 'repository', 'open source',
                'open-source', 'javascript', 'typescript', 'python', 'golang', 'rust lang',
                'c++', 'c#', 'kotlin', 'swift lang', 'php', 'ruby', 'sql', 'postgresql',
                'mysql', 'mongodb', 'redis', 'graphql', 'rest api', 'grpc', 'websocket',
                'react.js', 'react js', 'next.js', 'nextjs', 'vue.js', 'vuejs', 'nuxt',
                'angular', 'svelte', 'node.js', 'nodejs', 'deno', 'bun.sh', 'tailwind css',
                'webassembly', 'wasm', 'docker', 'kubernetes', 'k8s', 'terraform', 'ci/cd',
                'github actions', 'devops', 'microservices', 'serverless', 'sdk', 'npm package',
                'pypi', 'composer', 'cargo', 'framework', 'compiler', 'debugger', 'refactoring',
                'bare metal', 'cloud infrastructure', 'cloud native', 'api endpoint',
                'برمجة', 'تطوير برمجيات', 'مطور', 'مطورين', 'مبرمج', 'مبرمجين', 'كود', 'شيفرة',
                'مستودع كود', 'مفتوح المصدر', 'إطار عمل', 'مكتبة برمجية', 'واجهة برمجة التطبيقات',
                'قاعدة بيانات', 'قواعد بيانات', 'حاويات دوكر', 'كوبرنيتس', 'بناء وتجميع',
                'تصحيح أخطاء', 'هندسة البرمجيات', 'تطوير الويب', 'تطوير التطبيقات', 'خوارزميات',
                'بنية سحابية', 'خادم سحابي'
            ],
            'medium_priority' => [
                'developer', 'coding', 'backend', 'frontend', 'fullstack', 'library', 'runtime',
                'npm', 'git', 'commit', 'branch', 'syntax', 'stack trace',
                'واجهة أمامية', 'واجهة خلفية', 'مكتبة', 'أمر برمجي', 'أداء الكود'
            ]
        ],

        'general-tech' => [
            'name_ar' => 'تقنية عامة',
            'high_priority' => [
                'big tech', 'antitrust', 'ftc', 'doj', 'eu regulation', 'dma', 'dsa',
                'venture capital', 'startup funding', 'ipo', 'spacex', 'starlink',
                'elon musk', 'satya nadella', 'sundar pichai', 'tim cook', 'mark zuckerberg',
                'telecom', '5g network', '6g', 'broadband', 'fintech', 'stripe', 'crypto', 'bitcoin',
                'blockchain', 'digital economy', 'tech policy',
                'تقنية عامة', 'شركات تقنية', 'استحواذ', 'اندماج', 'اكتتاب', 'استثمار جريء',
                'شركات ناشئة', 'احتكار تقني', 'تنظيم رقمي', 'الاتحاد الأوروبي للتقنية',
                'سبيس إكس', 'ستارلينك', 'شبكات الاتصال', 'الجيل الخامس', 'اقتصاد رقمي',
                'عملات مشفرة', 'بلوك تشين', 'إيلون ماسك', 'تيم كوك', 'ساتيا ناديلا'
            ],
            'medium_priority' => [
                'technology', 'tech industry', 'silicon valley', 'layoffs', 'hiring',
                'market cap', 'quarterly earnings', 'revenue', 'cloud service', 'datacenter',
                'سوق التقنية', 'وادي السيليكون', 'أرباح فصلية', 'مراكز بيانات', 'خدمات سحابية'
            ]
        ],

        'mali-tiry' => [
            'name_ar' => 'عسكرية',
            'high_priority' => [
                'military', 'us army', 'army', 'pentagon', 'navy', 'air force',
                'marine corps', 'marines', 'aircraft carrier', 'fighter jet', 'bomber',
                'missile', 'guided missile', 'patriot missile', 'drone', 'uav',
                'weapons', 'ammunition', 'defense', 'troops', 'combat', 'warship',
                'frigate', 'destroyer', 'submarine', 'apache', 'northrop grumman',
                'military exercise', 'iraq', 'syria', 'hezbollah', 'houthi',
                'الجيش', 'الجيش الأمريكي', 'البنتاغون', 'البحرية', 'مشاة البحرية',
                'القوات المسلحة', 'القوات الجوية', 'القوات البرية',
                'حاملة الطائرات', 'حاملات الطائرات', 'مروحيات', 'أباتشي',
                'صاروخ', 'صواريخ', 'قاذفة', 'قاذفات', 'طائرات مسيرة', 'طائرة مسيرة',
                'مناورات', 'معملية عسكرية', 'قاعدة عسكرية', 'قواعد عسكرية',
                'جندي', 'جنود', 'ضباط', 'عسكري', 'عسكرية',
                'نورثروب جرومان', 'سكايديو', 'أسلحة', 'تسليح', 'ذخيرة', 'دبابة',
                'مدمرة', 'غواصة', 'سفينة حربية', 'صاروخ موجه', 'أنظمة صاروخية'
            ],
            'medium_priority' => [
                'troop', 'soldier', 'recruit', 'ordnance', 'deployment', 'artillery',
                'escort', 'patrol', 'airstrike', 'commander', 'headquarters', 'training',
                'قيادة عسكرية', 'تقرير رقابي', 'مناورة', 'خلل فني', 'إصابات',
                'أسطول', 'منظومة', 'ذخيرة حربية', 'سلاح', 'موقف دفاعي'
            ]
        ],

        'cya-sah' => [
            'name_ar' => 'سياسية',
            'high_priority' => [
                'politics', 'political', 'president', 'presidential', 'prime minister',
                'election', 'elections', 'parliament', 'congress', 'congressional',
                'senate', 'government', 'cabinet', 'minister', 'ministry', 'diplomacy',
                'diplomatic', 'ambassador', 'sanctions', 'united nations',
                'security council', 'summit', 'ceasefire', 'truce', 'peace talks',
                'negotiations', 'treaty', 'coalition', 'legislation', 'lawmaker',
                'policy', 'referendum', 'opposition', 'white house', 'netanyahu',
                'انتخابات', 'الرئيس', 'رئيس الوزراء', 'الحكومة', 'الوزراء', 'البرلمان',
                'الكونغرس', 'مجلس الأمن', 'الأمم المتحدة', 'وزارة الخارجية', 'الخارجية',
                'دبلوماسية', 'دبلوماسي', 'سفير', 'قمة', 'اتفاقية', 'هدنة',
                'وقف إطلاق النار', 'مفاوضات', 'استراتيجية', 'سياسي', 'سياسية',
                'الرؤية السياسية', 'ميزانية', 'تشريع', 'استجواب', 'محاكمة',
                'انقلاب', 'استقالة', 'معارضة', 'قرار دولي', 'مجلس النواب',
                'نتنياهو', 'إسرائيل', 'غزة', 'إيران'
            ],
            'medium_priority' => [
                'politicians', 'campaign', 'voters', 'reform', 'governance',
                'administration', 'party', 'parties', 'resolution', 'sanction',
                'human rights', 'corruption', 'protest', 'boycott',
                'حرب', 'حروب', 'أزمة', 'تسوية', 'موقف', 'بيان'
            ]
        ],

        'economic' => [
            'name_ar' => 'اقتصادية',
            'high_priority' => [
                'economy', 'economic', 'inflation', 'gdp', 'central bank', 'interest rate', 'federal reserve',
                'ecb', 'imf', 'world bank', 'unemployment', 'recession', 'stimulus', 'budget deficit',
                'trade deficit', 'exports', 'imports', 'tariff', 'tariffs', 'stock market', 'wall street',
                'nasdaq', 's&p 500', 'treasury yield', 'bond yield', 'oil price', 'crude oil', 'gold price',
                'commodity', 'commodities', 'supply chain', 'consumer prices', 'cpi', 'fiscal', 'monetary policy',
                'debt', 'default', 'bailout', 'bankrupt', 'merger', 'acquisition', 'antitrust settlement',
                'اقتصاد', 'اقتصادية', 'الاقتصاد', 'تضخم', 'الاحتياطي الفيدرالي', 'المجلس الاحتياطي',
                'البنك المركزي', 'البنوك المركزية', 'أسعار الفائدة', 'سعر الفائدة', 'الناتج المحلي', 'البطالة',
                'الركود', 'الكساد', 'الميزانية', 'العجز', 'الفائض', 'التعرفة', 'التعريفة', 'التجارة', 'التصدير',
                'الاستيراد', 'الأسهم', 'أسهم', 'البورصة', 'وول ستريت', 'النفط', 'الذهب', 'السلع', 'الضرائب',
                'الجمارك', 'العملة'
            ],
            'medium_priority' => [
                'prices', 'wages', 'salary', 'cost of living', 'consumer confidence', 'manufacturing',
                'retail sales', 'housing market', 'jobs', 'earnings', 'market', 'investors', 'portfolio', 'أسعار',
                'الأجور', 'الرواتب', 'تكلفة المعيشة', 'ثقة المستهلك', 'التصنيع', 'مبيعات التجزئة', 'سوق الإسكان',
                'الوظائف', 'أرباح', 'السوق', 'المستثمرون', 'محافظ مالية'
            ]
        ],

        'health' => [
            'name_ar' => 'صحيحة',
            'high_priority' => [
                'health', 'healthcare', 'hospital', 'medical', 'medicine', 'doctor', 'physician', 'nurse',
                'vaccine', 'vaccination', 'virus', 'outbreak', 'epidemic', 'pandemic', 'disease', 'infection',
                'cancer', 'diabetes', 'heart attack', 'stroke', 'surgery', 'drug', 'drugmaker', 'fda',
                'world health organization', 'mental health', 'depression', 'anxiety', 'obesity', 'nutrition',
                'malnutrition', 'covid', 'influenza', 'cholera', 'measles', 'mpox', 'clinical trial', 'patient',
                'صحة', 'صحيحة', 'الصحة', 'صحة عامة', 'مستشفى', 'المستشفيات', 'طبي', 'طبية', 'طبيب', 'أطباء',
                'ممرض', 'تمريض', 'لقاح', 'اللقاحات', 'تطعيم', 'فيروس', 'الفيروسات', 'وباء', 'جائحة', 'مرض',
                'الأمراض', 'عدوى', 'سرطان', 'سكري', 'نوبة قلبية', 'جلطة', 'جراحة', 'دواء', 'الأدوية', 'صحة نفسية',
                'الاكتئاب', 'القلق', 'التغذية', 'سوء التغذية', 'منظمة الصحة', 'الصحة العالمية', 'أمراض'
            ],
            'medium_priority' => [
                'symptoms', 'treatment', 'therapy', 'screening', 'immunization', 'hospitalized', 'clinic',
                'disease outbreak', 'أعراض', 'علاج', 'علاجية', 'فحص', 'تحصين', 'مُصاب', 'عيادة', 'تفشي', 'مستوصف'
            ]
        ],

        'sports' => [
            'name_ar' => 'رياضية',
            'high_priority' => [
                'football', 'soccer', 'basketball', 'tennis', 'volleyball', 'handball', 'cricket', 'rugby',
                'olympic', 'olympics', 'fifa', 'uefa', 'nba', 'nfl', 'formula 1', 'f1', 'premier league',
                'la liga', 'real madrid', 'barcelona', 'liverpool', 'manchester city', 'chelsea', 'arsenal',
                'transfer', 'striker', 'goal', 'match', 'tournament', 'championship', 'world cup', 'badminton',
                'swimming', 'marathon', 'athletics', 'referee', 'coach', 'stadium', 'playoffs', 'knockout',
                'friendly match', 'رياضة', 'رياضية', 'الرياضة', 'كرة القدم', 'كرة', 'الدوري', 'الدوريات', 'بطولة',
                'بطولات', 'كأس العالم', 'كأس', 'أولمبياد', 'الأولمبية', 'الأولمبياد', 'مباراة', 'مباريات', 'لاعب',
                'لاعبون', 'مدرب', 'الحكم', 'جمهور', 'ملعب', 'انتقالات', 'فيفاء', 'الاتحاد الدولي', 'ليفربول',
                'ريال مدريد', 'برشلونة', 'مانشستر', 'تشelsea', 'آرسنال', 'بطولة أوروبا', 'الدوري الأمريكي',
                'سباحة', 'جري', 'ماراثون'
            ],
            'medium_priority' => [
                'league', 'player', 'players', 'team', 'teams', 'score', 'win', 'loss', 'season', 'final', 'draw',
                'squad', 'manager', 'fans', 'cup', 'فريق', 'فرق', 'نتيجة', 'فوز', 'خسارة', 'موسم', 'نهائي',
                'تعادل', 'مدرب', 'مشجع', 'كأس'
            ]
        ],

        'science' => [
            'name_ar' => 'علوم',
            'high_priority' => [
                'science', 'scientist', 'research', 'study finds', 'discovery', 'telescope', 'nasa', 'space',
                'satellite', 'rocket launch', 'exoplanet', 'astronomy', 'physics', 'chemistry', 'biology',
                'genome', 'genetics', 'dna', 'crispr', 'quantum', 'nuclear fusion', 'particle', 'james webb',
                'mars', 'moon mission', 'space station', 'laboratory', 'peer-reviewed', 'experiment', 'astronaut',
                'solar system', 'علم', 'علوم', 'العلوم', 'عالم', 'علماء', 'العلماء', 'باحث', 'البحث', 'دراسة',
                'دراسات', 'اكتشاف', 'تلسكوب', 'ناسا', 'فضاء', 'الفضاء', 'قمر صناعي', 'كوكب', 'الكواكب', 'فلك',
                'علوم فلكية', 'رحلة فضاء', 'محطة فضائية', 'فيزياء', 'كيمياء', 'أحياء', 'بيولوجيا', 'دنا',
                'تعديل وراثي', 'خلية', 'مختبر', 'بحث علمي', 'دراسة علمية', 'اندماج نووي', 'حوسبة'
            ],
            'medium_priority' => [
                'physics', 'experiment results', 'cells', 'protein', 'species', 'ecosystem', 'data analysis',
                'theory', 'تجربة', 'نتائج', 'بروتوكول', 'نوع', 'جزيء', 'نظرية', 'رياضيات', 'إحصاء'
            ]
        ],

        'technology' => [
            'name_ar' => 'تقنية',
            'high_priority' => [
                'technology', 'tech', 'smartphone', 'app', 'software', 'hardware', 'chip', 'semiconductor',
                'cloud computing', 'internet', 'social media', 'data center', 'algorithm', 'open source',
                'developer', 'cybersecurity', 'hacking', 'artificial intelligence', 'robot', 'chipmaker', 'linux',
                'google', 'microsoft', 'apple', 'samsung', 'tesla', 'nvidia', '5g', 'web', 'website', 'browser',
                'startup', 'app store', 'update', 'تقنية', 'تكنولوجيا', 'التقنية', 'تقنية المعلومات', 'هاتف',
                'هواتف', 'هاتف ذكي', 'تطبيق', 'تطبيقات', 'برمجيات', 'عتاد', 'شريحة', 'معالج', 'ذكاء اصطناعي',
                'روبوت', 'حوسبة سحابية', 'الإنترنت', 'مواقع', 'وسائل التواصل', 'خوارزمية', 'مصدر مفتوح', 'مطور',
                'اختراق', 'أمن سيبراني', 'قرصنة', 'جوجل', 'مايكروسوفت', 'آبل', 'سامسونج', 'تسلا', 'إنفيديا',
                'شبكة', 'برمجة', 'كود', 'تحديث'
            ],
            'medium_priority' => [
                'device', 'gadget', 'network', 'platform', 'user', 'interface', 'version', 'release', 'beta',
                'جهاز', 'أجهزة', 'منصة', 'مستخدم', 'واجهة', 'إصدار', 'نسخة', 'شبكات'
            ]
        ],

        'society' => [
            'name_ar' => 'اجتماعية',
            'high_priority' => [
                'society', 'social', 'community', 'protest', 'demonstration', 'immigration', 'migrants',
                'refugees', 'education', 'school', 'university', 'student', 'teacher', 'housing', 'poverty',
                'inequality', 'crime', 'murder', 'accident', 'earthquake', 'flood', 'wildfire', 'climate change',
                'environment', 'pollution', 'charity', 'ngo', 'human rights', 'womens rights', 'family',
                'marriage', 'culture', 'heritage', 'disaster', 'heatwave', 'drought', 'اجتماعية', 'مجتمع',
                'المجتمع', 'احتجاج', 'تظاهرة', 'تظاجهات', 'مهاجرون', 'مهاجرين', 'لاجئون', 'لاجئين', 'تعليم',
                'مدارس', 'جامعات', 'طلاب', 'معلم', 'إسكان', 'فقر', 'فقراء', 'جريمة', 'قتل', 'حادث', 'زلزال',
                'فيضان', 'فيضانات', 'حرائق', 'تغير المناخ', 'مناخ', 'بيئة', 'تلوث', 'جمعيات', 'حقوق الإنسان',
                'حقوق المرأة', 'ثقافة', 'تراث', 'كارثة', 'موجة حر', 'جفاف'
            ],
            'medium_priority' => [
                'women', 'youth', 'elderly', 'community groups', 'volunteers', 'fundraising', 'public opinion',
                'survey', 'نساء', 'شباب', 'كبار السن', 'متطوعون', 'تبرع', 'رأي عام', 'استطلاع'
            ]
        ]
    ];

    private static $sourceRulesFile = null;

    private static $defaultSourceRules = [
        'abc news au - world' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'abc news: international' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'aerotime' => ['mode' => 'smart', 'target_slug' => 'mali-tiry', 'weight' => 6.0],
        'airforce technology' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 8.0],
        'al arabiya english (العربية)' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'al jazeera english (الجزيرة)' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'al jazeera – breaking news, world news and video from al jazeera' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'amnesty international' => ['mode' => 'smart', 'target_slug' => 'society', 'weight' => 5.0],
        'army times' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 10.0],
        'ars technica' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'aviation week network' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 8.0],
        'bbc arabic' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'bbc news' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'bbc news - technology' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'bbc uk politics' => ['mode' => 'strict', 'target_slug' => 'cya-sah', 'weight' => 10.0],
        'bbc world (بي بي سي)' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'bleeping computer (أمن معلومات)' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 10.0],
        'blog daily listings rss' => ['mode' => 'strict', 'target_slug' => 'cya-sah', 'weight' => 10.0],
        'bloomberg (عبر google news)' => ['mode' => 'strict', 'target_slug' => 'economic', 'weight' => 9.0],
        'breaking defense' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 10.0],
        'china defense blog' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'cnbc' => ['mode' => 'smart', 'target_slug' => 'economic', 'weight' => 4.0],
        'cnbc economy' => ['mode' => 'strict', 'target_slug' => 'economic', 'weight' => 9.0],
        'cnbc top news' => ['mode' => 'smart', 'target_slug' => 'economic', 'weight' => 4.0],
        'cnn - app tech section' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'cnn world' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'cnn.com - rss channel - app international edition' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'cnn.com - rss channel - world' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'coindesk' => ['mode' => 'smart', 'target_slug' => 'economic', 'weight' => 6.0],
        'cointelegraph' => ['mode' => 'smart', 'target_slug' => 'economic', 'weight' => 6.0],
        'defense news' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 10.0],
        'defense one' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 10.0],
        'defense one - all content' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 10.0],
        'defense.gov explore feed' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 10.0],
        'der spiegel international' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'drone wars // война дронов' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'dw - all news (دويتشه فيله)' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'dw - politics' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'engadget' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'euronews - world' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'fararu | فرارو | اخبار روز ایران و جهان' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'financial times - home' => ['mode' => 'strict', 'target_slug' => 'economic', 'weight' => 9.0],
        'foreign affairs' => ['mode' => 'strict', 'target_slug' => 'cya-sah', 'weight' => 9.0],
        'foreign policy' => ['mode' => 'strict', 'target_slug' => 'cya-sah', 'weight' => 9.0],
        'france 24 - news' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'freshrss releases' => ['mode' => 'smart', 'target_slug' => 'technology', 'weight' => 6.0],
        'gizmodo' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'globalsecurity.org' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'haaretz - world' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'hacker news' => ['mode' => 'smart', 'target_slug' => 'technology', 'weight' => 7.0],
        'investing.com - news' => ['mode' => 'strict', 'target_slug' => 'economic', 'weight' => 9.0],
        'jane\'s (مميز - جزئي)' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 10.0],
        'krebs on security' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 10.0],
        'latest & breaking news on fox news' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'maariv.co.il - צבא וביטחון' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'maariv.co.il - الأخبار في إسرائيل' => ['mode' => 'smart', 'target_slug' => 'society', 'weight' => 4.0],
        'maariv.co.il - خبر عاجل' => ['mode' => 'smart', 'target_slug' => 'general-news', 'weight' => 3.0],
        'maariv.co.il - سياسي - سياسي' => ['mode' => 'strict', 'target_slug' => 'cya-sah', 'weight' => 8.0],
        'maariv.co.il - معاريف' => ['mode' => 'smart', 'target_slug' => 'general-news', 'weight' => 2.0],
        'mashable - tech' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'military times' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 10.0],
        'military.com - news' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 10.0],
        'mit technology review' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'nasa news (فضاء)' => ['mode' => 'strict', 'target_slug' => 'science', 'weight' => 9.0],
        'nato news (ناتو)' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 8.0],
        'navy times' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 10.0],
        'ncsc news feed' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 10.0],
        'news' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 10.0],
        'npr - world' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'nyt - politics' => ['mode' => 'strict', 'target_slug' => 'cya-sah', 'weight' => 10.0],
        'nyt - world (نيويورك تايمز)' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'nyt > top stories' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'nyt > world news' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'oilprice.com (طاقة)' => ['mode' => 'strict', 'target_slug' => 'economic', 'weight' => 9.0],
        'reddit - r/asksocialscience' => ['mode' => 'smart', 'target_slug' => 'society', 'weight' => 4.0],
        'reddit - r/combatfootage' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'reddit - r/credibledefense' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'reddit - r/cybersecurity' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'reddit - r/europe' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'reddit - r/geopolitics' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'reddit - r/geopolitics (سياق دفاعي)' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'reddit - r/internationalpolitics' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'reddit - r/linux' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'reddit - r/military' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'reddit - r/militaryhistory' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'reddit - r/netsec' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'reddit - r/neutralpolitics' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'reddit - r/politicaldiscussion' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'reddit - r/politics' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'reddit - r/programming' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'reddit - r/selfhosted' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'reddit - r/sysadmin' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'reddit - r/technology' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'reddit - r/warcollege' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'reddit - r/worldnews' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'rt arabic' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'rt world news' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'rusi (دراسات دفاعية)' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'server fault (سيرفرات وشبكات)' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'simple flying' => ['mode' => 'smart', 'target_slug' => 'mali-tiry', 'weight' => 6.0],
        'sky news' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'sky news - world' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'stack overflow - android' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'stack overflow - c++' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'stack overflow - javascript' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'stack overflow - python' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'stack overflow - أحدث الأسئلة' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'stratfor worldview' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'super user (مشاكل كمبيوتر)' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 8.0],
        'techcrunch' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'technology | the guardian' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'the associated press / @ap' => ['mode' => 'smart', 'target_slug' => 'general-news', 'weight' => 3.0],
        'the atlantic' => ['mode' => 'strict', 'target_slug' => 'cya-sah', 'weight' => 9.0],
        'the aviationist' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 8.0],
        'the conversation (تحليلات)' => ['mode' => 'smart', 'target_slug' => 'society', 'weight' => 4.0],
        'the economist - economics' => ['mode' => 'strict', 'target_slug' => 'economic', 'weight' => 9.0],
        'the economist - finance' => ['mode' => 'strict', 'target_slug' => 'economic', 'weight' => 9.0],
        'the guardian - politics' => ['mode' => 'strict', 'target_slug' => 'cya-sah', 'weight' => 10.0],
        'the guardian - society' => ['mode' => 'strict', 'target_slug' => 'society', 'weight' => 8.0],
        'the independent - world' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'the japan times' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'the next web' => ['mode' => 'smart', 'target_slug' => 'technology', 'weight' => 6.0],
        'the straits times - world' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'the verge' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'the war zone (twz)' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'times of india - top' => ['mode' => 'smart', 'target_slug' => 'general-news', 'weight' => 3.0],
        'times of israel' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 6.0],
        'trt world' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'washington post - politics' => ['mode' => 'strict', 'target_slug' => 'cya-sah', 'weight' => 9.0],
        'white house.gov press office feed' => ['mode' => 'strict', 'target_slug' => 'cya-sah', 'weight' => 10.0],
        'wired' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'world' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'world - cbsnews.com' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'world economic forum' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'world news | the guardian' => ['mode' => 'strict', 'target_slug' => 'cya-sah', 'weight' => 8.0],
        'wsj - us business' => ['mode' => 'strict', 'target_slug' => 'economic', 'weight' => 9.0],
        'zdnet' => ['mode' => 'strict', 'target_slug' => 'technology', 'weight' => 9.0],
        'חמ"ל: חדשות ועדכונים בזמן אמת' => ['mode' => 'smart', 'target_slug' => 'general-news', 'weight' => 3.0],
        'أخبار الدفاع العربي' => ['mode' => 'strict', 'target_slug' => 'mali-tiry', 'weight' => 9.0],
        'أخبار اليوم | سكاي نيوز عربية' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
        'الجزيرة : صفحة المحليات' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 5.0],
        'فرانس 24 – الأخبار والأحداث الدولية على مدار الساعة' => ['mode' => 'smart', 'target_slug' => 'cya-sah', 'weight' => 4.0],
    ];

    private static function getStoragePath(): string
    {
        if (self::$rulesFile === null) {
            self::$rulesFile = dirname(__DIR__) . '/config/classifier_rules.json';
        }
        return self::$rulesFile;
    }

    private static function getSourceRulesPath(): string
    {
        if (self::$sourceRulesFile === null) {
            self::$sourceRulesFile = dirname(__DIR__) . '/config/source_classifier_rules.json';
        }
        return self::$sourceRulesFile;
    }

    /**
     * Get active rules (from JSON file if exists, or default)
     */
    public static function getRules(): array
    {
        $file = self::getStoragePath();
        if (file_exists($file)) {
            $json = @json_decode(file_get_contents($file), true);
            if (is_array($json) && !empty($json)) {
                return $json;
            }
        }
        // Initialize file if not exists
        @file_put_contents($file, json_encode(self::$defaultRules, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return self::$defaultRules;
    }

    /**
     * Save rules to JSON
     */
    public static function saveRules(array $rules): bool
    {
        $file = self::getStoragePath();
        return (bool) @file_put_contents($file, json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Get Source Classification Rules
     */
    public static function getSourceRules(): array
    {
        $file = self::getSourceRulesPath();
        if (file_exists($file)) {
            $json = @json_decode(file_get_contents($file), true);
            if (is_array($json)) {
                return $json;
            }
        }
        @file_put_contents($file, json_encode(self::$defaultSourceRules, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return self::$defaultSourceRules;
    }

    /**
     * Save Source Classification Rules
     */
    public static function saveSourceRules(array $rules): bool
    {
        $file = self::getSourceRulesPath();
        return (bool) @file_put_contents($file, json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Update rule for a single source
     */
    public static function setSourceRule(string $sourceName, string $mode, string $targetSlug, float $weight = 5.0): bool
    {
        $rules = self::getSourceRules();
        $key = mb_strtolower(trim($sourceName));
        $rules[$key] = [
            'mode'        => in_array($mode, ['strict', 'smart', 'neutral'], true) ? $mode : 'smart',
            'target_slug' => $targetSlug,
            'weight'      => max(0.0, min(15.0, $weight))
        ];
        return self::saveSourceRules($rules);
    }

    /**
     * Check if a keyword conflicts with other categories
     */
    public static function checkKeywordConflicts(string $keyword, string $targetCategorySlug = ''): array
    {
        $rules = self::getRules();
        $keywordLower = mb_strtolower(trim($keyword));
        $conflicts = [];

        foreach ($rules as $slug => $data) {
            if ($slug === $targetCategorySlug) continue;

            $high = array_map('mb_strtolower', $data['high_priority'] ?? []);
            $med  = array_map('mb_strtolower', $data['medium_priority'] ?? []);

            if (in_array($keywordLower, $high, true) || in_array($keywordLower, $med, true)) {
                $priority = in_array($keywordLower, $high, true) ? 'عالية (High)' : 'متوسطة (Medium)';
                $conflicts[] = [
                    'category_slug' => $slug,
                    'category_name' => $data['name_ar'] ?? $slug,
                    'priority'      => $priority
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Scan the entire dictionary and find all cross-category duplicate keywords
     */
    public static function getAllConflicts(): array
    {
        $rules = self::getRules();
        $seen = [];
        $conflicts = [];

        foreach ($rules as $slug => $data) {
            $catName = $data['name_ar'] ?? $slug;
            $allKw = array_merge(
                array_map('mb_strtolower', $data['high_priority'] ?? []),
                array_map('mb_strtolower', $data['medium_priority'] ?? [])
            );

            foreach ($allKw as $kw) {
                $kw = trim($kw);
                if (empty($kw)) continue;

                if (isset($seen[$kw])) {
                    $conflicts[$kw][] = [
                        'slug' => $slug,
                        'name' => $catName
                    ];
                    // Also make sure original category is listed
                    $first = $seen[$kw];
                    if (!in_array($first, array_column($conflicts[$kw], 'slug'), true)) {
                        array_unshift($conflicts[$kw], [
                            'slug' => $first['slug'],
                            'name' => $first['name']
                        ]);
                    }
                } else {
                    $seen[$kw] = [
                        'slug' => $slug,
                        'name' => $catName
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Add a new keyword with conflict detection
     */
    public static function addKeyword(string $categorySlug, string $keyword, string $priority = 'high'): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return ['ok' => false, 'error' => 'المصطلح لا يمكن أن يكون فارغاً.'];
        }

        $conflicts = self::checkKeywordConflicts($keyword, $categorySlug);
        $rules = self::getRules();

        if (!isset($rules[$categorySlug])) {
            return ['ok' => false, 'error' => 'التصنيف المطلوب غير موجود.'];
        }

        $bucket = ($priority === 'medium') ? 'medium_priority' : 'high_priority';
        if (!isset($rules[$categorySlug][$bucket])) {
            $rules[$categorySlug][$bucket] = [];
        }

        // Avoid adding duplicate in same category
        $currentLower = array_map('mb_strtolower', $rules[$categorySlug][$bucket]);
        if (in_array(mb_strtolower($keyword), $currentLower, true)) {
            return ['ok' => false, 'error' => 'هذا المصطلح مضاف بالفعل في هذا التصنيف.'];
        }

        array_unshift($rules[$categorySlug][$bucket], $keyword);
        self::saveRules($rules);

        return [
            'ok'        => true,
            'keyword'   => $keyword,
            'priority'  => $priority,
            'conflicts' => $conflicts
        ];
    }

    /**
     * Delete a keyword from a category
     */
    public static function removeKeyword(string $categorySlug, string $keyword): bool
    {
        $rules = self::getRules();
        if (!isset($rules[$categorySlug])) return false;

        $keywordLower = mb_strtolower(trim($keyword));
        $modified = false;

        foreach (['high_priority', 'medium_priority'] as $bucket) {
            if (isset($rules[$categorySlug][$bucket])) {
                $rules[$categorySlug][$bucket] = array_values(array_filter($rules[$categorySlug][$bucket], function($k) use ($keywordLower, &$modified) {
                    if (mb_strtolower(trim($k)) === $keywordLower) {
                        $modified = true;
                        return false;
                    }
                    return true;
                }));
            }
        }

        if ($modified) {
            self::saveRules($rules);
        }
        return $modified;
    }

    /**
     * Smart Source-Aware Weighted Classification Engine
     */
    public static function classify(
        string $titleEn, 
        string $contentEn, 
        string $titleAr = '', 
        int $sourceCatId = 0, 
        array $catSlugMap = [],
        string $sourceName = '',
        string $articleUrl = ''
    ): int {
        $rules = self::getRules();
        $sourceRules = self::getSourceRules();

        $titleCombined = mb_strtolower($titleEn . ' ' . $titleAr);
        $contentSample = mb_strtolower(mb_substr($contentEn, 0, 2000));
        $sourceNameClean = mb_strtolower(trim($sourceName));

        // ─── 1. Check for Strict Source Override ────────────────────
        if (!empty($sourceNameClean) && isset($sourceRules[$sourceNameClean])) {
            $sRule = $sourceRules[$sourceNameClean];
            if (($sRule['mode'] ?? '') === 'strict' && !empty($sRule['target_slug'])) {
                $targetSlug = $sRule['target_slug'];
                if (isset($catSlugMap[$targetSlug])) {
                    return (int) $catSlugMap[$targetSlug];
                }
            }
        }

        // Initialize Category Scores
        // Only score categories that actually exist in the database (catSlugMap),
        // otherwise a rule for a missing category could win and force a bogus id.
        $scoredSlugs = array_keys($rules);
        if (!empty($catSlugMap)) {
            $existing = array_values(array_intersect($scoredSlugs, array_keys($catSlugMap)));
            if (!empty($existing)) {
                $scoredSlugs = $existing;
            }
        }

        $scores = [];
        foreach ($scoredSlugs as $slug) {
            $scores[$slug] = 0.0;
        }

        // ─── 2. Source Prior Weighting (Smart Mode) ────────────────
        if (!empty($sourceNameClean) && isset($sourceRules[$sourceNameClean])) {
            $sRule = $sourceRules[$sourceNameClean];
            $tSlug = $sRule['target_slug'] ?? '';
            $sWeight = (float) ($sRule['weight'] ?? 0.0);
            if (($sRule['mode'] ?? '') === 'smart' && isset($scores[$tSlug])) {
                $scores[$tSlug] += $sWeight;
            }
        } elseif ($sourceCatId > 0 && !empty($catSlugMap)) {
            $reverseMap = array_flip($catSlugMap);
            $presetSlug = $reverseMap[$sourceCatId] ?? '';
            if ($presetSlug !== '' && isset($scores[$presetSlug])) {
                $scores[$presetSlug] += 3.0;
            }
        }

        // ─── 3. Article URL Category / Tag Path Signals ─────────────
        if (!empty($articleUrl)) {
            $urlLower = mb_strtolower($articleUrl);
            // AI URL signals
            if (preg_match('#/(artificial-intelligence|ai|machine-learning|generative-ai|chatgpt|openai)/#i', $urlLower)) {
                if (isset($scores['artificial-intelligence'])) $scores['artificial-intelligence'] += 5.0;
            }
            // Security URL signals
            if (preg_match('#/(cybersecurity|security|privacy|malware|vulnerabilities|hacks)/#i', $urlLower)) {
                if (isset($scores['cybersecurity'])) $scores['cybersecurity'] += 5.0;
            }
            // Hardware URL signals
            if (preg_match('#/(hardware|gadgets|phones|mobile|laptops|reviews|devices|smartphones)/#i', $urlLower)) {
                if (isset($scores['hardware-devices'])) $scores['hardware-devices'] += 5.0;
            }
            // Software Dev URL signals
            if (preg_match('#/(developer|programming|coding|software-development|devops|open-source)/#i', $urlLower)) {
                if (isset($scores['software-development'])) $scores['software-development'] += 5.0;
            }
        }

        // ─── 4. Specific High-Confidence Regex Pattern Matchers ─────
        // Go / Golang releases
        if (preg_match('/\bgo\s+\d+(\.\d+)*\b/i', $titleCombined) || preg_match('/\bgolang\b/i', $titleCombined)) {
            if (isset($scores['software-development'])) $scores['software-development'] += 10.0;
        }
        // CVE Vulnerabilities
        if (preg_match('/\bcve-\d{4}-\d+\b/i', $titleCombined)) {
            if (isset($scores['cybersecurity'])) $scores['cybersecurity'] += 10.0;
        }
        // Specific GPUs / CPUs
        if (preg_match('/\b(rtx\s*50\d0|ryzen\s*\d{4}|intel\s*core\s*ultra|snapdragon\s*8\s*elite)\b/i', $titleCombined)) {
            if (isset($scores['hardware-devices'])) $scores['hardware-devices'] += 10.0;
        }
        // Specific AI Models
        if (preg_match('/\b(claude\s*3\.\d|gpt-4o|deepseek-r1|gemini\s*2\.\d|sora|o3-mini)\b/i', $titleCombined)) {
            if (isset($scores['artificial-intelligence'])) $scores['artificial-intelligence'] += 10.0;
        }

        // ─── 5. Calculate Title & Content Keyword Scores ────────────
        foreach ($scores as $slug => $unusedScore) {
            $data = $rules[$slug] ?? null;
            if (!is_array($data)) continue;

            // High-priority matches in TITLE (Score: 6.0 each)
            foreach (($data['high_priority'] ?? []) as $kw) {
                if (self::containsKeyword($titleCombined, $kw)) {
                    $scores[$slug] += 6.0;
                }
                // High-priority matches in CONTENT (Score: 2.0 each)
                if (self::containsKeyword($contentSample, $kw)) {
                    $scores[$slug] += 2.0;
                }
            }

            // Medium-priority matches in TITLE (Score: 3.0 each)
            foreach (($data['medium_priority'] ?? []) as $kw) {
                if (self::containsKeyword($titleCombined, $kw)) {
                    $scores[$slug] += 3.0;
                }
                // Medium-priority matches in CONTENT (Score: 1.0 each)
                if (self::containsKeyword($contentSample, $kw)) {
                    $scores[$slug] += 1.0;
                }
            }
        }

        // ─── 6. Find the Category with the Highest Score ────────────
        arsort($scores);
        $bestSlug = (string) key($scores);
        $topScore = (float) current($scores);

        // Fallback if score is too low or neutral
        if ($topScore < 2.5) {
            if ($sourceCatId > 0 && in_array($sourceCatId, $catSlugMap, true)) {
                return $sourceCatId;
            }
            $bestSlug = 'general-news';
        }

        if (isset($catSlugMap[$bestSlug])) {
            return (int) $catSlugMap[$bestSlug];
        }

        // Last resort: any real category id (never a hardcoded id that may not exist)
        if (!empty($catSlugMap)) {
            return (int) reset($catSlugMap);
        }

        return $sourceCatId > 0 ? $sourceCatId : 0;
    }

    private static function containsKeyword(string $text, string $kw): bool
    {
        $kw = mb_strtolower(trim($kw));
        if ($kw === '') return false;

        if (mb_strlen($kw) <= 3) {
            return (bool) preg_match('/(?:^|[^a-z0-9\x{0600}-\x{06FF}])' . preg_quote($kw, '/') . '(?:$|[^a-z0-9\x{0600}-\x{06FF}])/ui', $text);
        }

        return (mb_strpos($text, $kw) !== false);
    }
}

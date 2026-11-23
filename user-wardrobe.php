<?php
session_start();
$userEmail = (string)($_SESSION['user_email'] ?? 'traveler@seasonverse.com');
$userName = (string)($_SESSION['user_name'] ?? 'Traveler');
$userInitial = strtoupper(substr(trim($userName),0,1)) ?: 'T';
$pdo = null;
$isAjax = $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']);
function wardrobeJson(array $data,int $code=200): never {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
function wardrobeProgress(PDO $pdo,string $email): array {
    $q=$pdo->prepare("SELECT id,item_name,is_packed FROM packing_checklist WHERE user_email=? ORDER BY id DESC");
    $q->execute([$email]); $items=$q->fetchAll(PDO::FETCH_ASSOC);
    $total=count($items); $packed=count(array_filter($items,fn($x)=>(int)$x['is_packed']===1));
    return ['items'=>$items,'total'=>$total,'packed'=>$packed,'percent'=>$total?(int)round($packed/$total*100):0];
}
try {
    $pdo=new PDO("mysql:host=localhost;dbname=seasonverse_db;charset=utf8mb4","root","",[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS wardrobe_items(id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(100) NOT NULL,category VARCHAR(50) NOT NULL,image_url VARCHAR(500) DEFAULT NULL,user_email VARCHAR(150) DEFAULT NULL,color VARCHAR(40) DEFAULT NULL,season VARCHAR(40) DEFAULT 'All Season',notes VARCHAR(255) DEFAULT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS packing_checklist(id INT AUTO_INCREMENT PRIMARY KEY,user_email VARCHAR(150) NOT NULL,item_name VARCHAR(150) NOT NULL,is_packed TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_pack_user(user_email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $cols=$pdo->query("SHOW COLUMNS FROM wardrobe_items")->fetchAll(PDO::FETCH_COLUMN);
    foreach(['user_email'=>"ALTER TABLE wardrobe_items ADD user_email VARCHAR(150) DEFAULT NULL",'color'=>"ALTER TABLE wardrobe_items ADD color VARCHAR(40) DEFAULT NULL",'season'=>"ALTER TABLE wardrobe_items ADD season VARCHAR(40) DEFAULT 'All Season'",'notes'=>"ALTER TABLE wardrobe_items ADD notes VARCHAR(255) DEFAULT NULL",'created_at'=>"ALTER TABLE wardrobe_items ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"] as $col=>$sql) if(!in_array($col,$cols,true)) $pdo->exec($sql);
    $starter=$pdo->prepare("SELECT COUNT(*) FROM packing_checklist WHERE user_email=?");$starter->execute([$userEmail]);
    if((int)$starter->fetchColumn()===0){
        $defaults=[['Wool Overcoat',1],['Merino Wool Scarf',1],['Leather Boots',0],['Cashmere Knitwear',0],['Thermal Base Layer',0]];
        $addStarter=$pdo->prepare("INSERT INTO packing_checklist(user_email,item_name,is_packed) VALUES(?,?,?)");
        foreach($defaults as $entry)$addStarter->execute([$userEmail,$entry[0],$entry[1]]);
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers(id INT AUTO_INCREMENT PRIMARY KEY,email VARCHAR(190) NOT NULL UNIQUE,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if(empty($_SESSION['wardrobe_csrf'])) $_SESSION['wardrobe_csrf']=bin2hex(random_bytes(32));
    $csrf=$_SESSION['wardrobe_csrf'];
    if((int)$pdo->query("SELECT COUNT(*) FROM wardrobe_items")->fetchColumn()===0){
        $seed=[
            ['Cashmere Knit','Tops','https://images.unsplash.com/photo-1434389677669-e08b4cac3105?auto=format&fit=crop&w=600&q=85','Winter'],
            ['Wool Trousers','Bottoms','https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?auto=format&fit=crop&w=600&q=85','Winter'],
            ['Alpine Overcoat','Outerwear','https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&w=600&q=85','Winter'],
            ['Leather Boots','Footwear','https://images.unsplash.com/photo-1520639888713-7851133b1ed0?auto=format&fit=crop&w=600&q=85','All Season'],
            ['Merino Scarf','Accessories','https://images.unsplash.com/photo-1520903920243-00d872a2d1c9?auto=format&fit=crop&w=600&q=85','Winter'],
            ['Leather Bag','Accessories','https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=600&q=85','All Season']
        ];
        $s=$pdo->prepare("INSERT INTO wardrobe_items(title,category,image_url,season) VALUES(?,?,?,?)"); foreach($seed as $r)$s->execute($r);
    }
    if($isAjax){
        if(!hash_equals($csrf,(string)($_POST['csrf']??''))) wardrobeJson(['status'=>'error','message'=>'Session expired. Refresh and try again.'],419);
        $action=(string)$_POST['action']; $id=filter_var($_POST['id']??$_POST['item_id']??null,FILTER_VALIDATE_INT);
        $clean=fn($v,$n=150)=>mb_substr(trim((string)$v),0,$n);
        if($action==='toggle_check'){
            if(!$id)wardrobeJson(['status'=>'error','message'=>'Invalid checklist item.'],400);
            $s=$pdo->prepare("UPDATE packing_checklist SET is_packed=? WHERE id=? AND user_email=?");$s->execute([(int)($_POST['status']??0)===1?1:0,$id,$userEmail]);
            $exists=$pdo->prepare("SELECT id FROM packing_checklist WHERE id=? AND user_email=?");$exists->execute([$id,$userEmail]);if(!$exists->fetch())wardrobeJson(['status'=>'error','message'=>'Checklist item not found.'],404);
            wardrobeJson(['status'=>'success']+wardrobeProgress($pdo,$userEmail));
        }
        if($action==='add_check'){
            $name=$clean($_POST['item_name']??'',150);if($name==='')wardrobeJson(['status'=>'error','message'=>'Enter an item name.'],422);
            $s=$pdo->prepare("SELECT id FROM packing_checklist WHERE user_email=? AND LOWER(item_name)=LOWER(?)");$s->execute([$userEmail,$name]);
            if(!$s->fetch())$pdo->prepare("INSERT INTO packing_checklist(user_email,item_name) VALUES(?,?)")->execute([$userEmail,$name]);
            wardrobeJson(['status'=>'success']+wardrobeProgress($pdo,$userEmail));
        }
        if($action==='remove_check'){
            if(!$id)wardrobeJson(['status'=>'error','message'=>'Invalid checklist item.'],400);
            $pdo->prepare("DELETE FROM packing_checklist WHERE id=? AND user_email=?")->execute([$id,$userEmail]);wardrobeJson(['status'=>'success']+wardrobeProgress($pdo,$userEmail));
        }
        if($action==='subscribe_newsletter'){
            $email=filter_var(trim((string)($_POST['email']??'')),FILTER_VALIDATE_EMAIL);
            if(!$email)wardrobeJson(['status'=>'error','message'=>'Enter a valid email address.'],422);
            try{$pdo->prepare("INSERT INTO newsletter_subscribers(email) VALUES(?)")->execute([$email]);}
            catch(PDOException $e){if($e->getCode()!=='23000')throw $e;}
            wardrobeJson(['status'=>'success','message'=>'You are subscribed to SeasonVerse updates.']);
        }
        if($action==='save_checklist')wardrobeJson(['status'=>'success']+wardrobeProgress($pdo,$userEmail));
        if(in_array($action,['add_wardrobe','update_wardrobe'],true)){
            $title=$clean($_POST['title']??'',100);$cat=$clean($_POST['category']??'',50);$image=$clean($_POST['image_url']??'',500);
            $color=$clean($_POST['color']??'',40);$season=$clean($_POST['season']??'All Season',40);$notes=$clean($_POST['notes']??'',255);
            if($title===''||$cat==='')wardrobeJson(['status'=>'error','message'=>'Item name and category are required.'],422);
            if($image!==''&&!filter_var($image,FILTER_VALIDATE_URL))wardrobeJson(['status'=>'error','message'=>'Enter a valid image URL.'],422);
            if($action==='add_wardrobe')$pdo->prepare("INSERT INTO wardrobe_items(title,category,image_url,user_email,color,season,notes) VALUES(?,?,?,?,?,?,?)")->execute([$title,$cat,$image?:null,$userEmail,$color,$season,$notes]);
            else{
                if(!$id)wardrobeJson(['status'=>'error','message'=>'Invalid wardrobe item.'],400);
                $s=$pdo->prepare("UPDATE wardrobe_items SET title=?,category=?,image_url=?,color=?,season=?,notes=? WHERE id=? AND user_email=?");$s->execute([$title,$cat,$image?:null,$color,$season,$notes,$id,$userEmail]);
                $c=$pdo->prepare("SELECT id FROM wardrobe_items WHERE id=? AND user_email=?");$c->execute([$id,$userEmail]);if(!$c->fetch())wardrobeJson(['status'=>'error','message'=>'You can only edit your own items.'],404);
            }
            $s=$pdo->prepare("SELECT * FROM wardrobe_items WHERE user_email IS NULL OR user_email=? ORDER BY user_email IS NOT NULL DESC,id DESC");$s->execute([$userEmail]);wardrobeJson(['status'=>'success','items'=>$s->fetchAll()]);
        }
        if($action==='delete_wardrobe'){
            if(!$id)wardrobeJson(['status'=>'error','message'=>'Invalid wardrobe item.'],400);
            $s=$pdo->prepare("DELETE FROM wardrobe_items WHERE id=? AND user_email=?");$s->execute([$id,$userEmail]);if(!$s->rowCount())wardrobeJson(['status'=>'error','message'=>'Only your own items can be deleted.'],404);wardrobeJson(['status'=>'success']);
        }
        if($action==='generate_wardrobe'){
            $dest=$clean($_POST['destination']??'Swiss Alps',100);$season=$clean($_POST['season']??'Winter',30);$style=$clean($_POST['style']??'Minimalist',30);
            $warm=in_array(strtolower($season),['winter','autumn'],true);$hot=strtolower($season)==='summer';
            $items=$warm?['Thermal base layer','Warm knit sweater','Insulated jacket','Wool trousers','Warm socks','Weatherproof boots','Scarf','Gloves','Travel outfit','Sleepwear','Toiletries']:($hot?['Breathable T-shirts','Lightweight trousers','Shorts','Walking shoes','Sandals','Sunglasses','Cap or hat','Light jacket','Travel outfit','Sleepwear','Toiletries']:['Light layers','Everyday shirts','Comfortable trousers','Light jacket','Walking shoes','Scarf','Travel outfit','Sleepwear','Toiletries']);
            if(strtolower($style)==='formal')array_unshift($items,'Formal outfit');if(strtolower($style)==='adventure')$items=array_merge($items,['Hiking shoes','Rain jacket','Day backpack','Reusable water bottle']);
            $find=$pdo->prepare("SELECT id FROM packing_checklist WHERE user_email=? AND LOWER(item_name)=LOWER(?)");$add=$pdo->prepare("INSERT INTO packing_checklist(user_email,item_name) VALUES(?,?)");$n=0;
            foreach(array_unique($items) as $item){$find->execute([$userEmail,$item]);if(!$find->fetch()){$add->execute([$userEmail,$item]);$n++;}}
            wardrobeJson(['status'=>'success','message'=>"$n packing suggestions added for $dest."]+wardrobeProgress($pdo,$userEmail));
        }
        wardrobeJson(['status'=>'error','message'=>'Unsupported action.'],400);
    }
    $s=$pdo->prepare("SELECT * FROM wardrobe_items WHERE user_email IS NULL OR user_email=? ORDER BY user_email IS NOT NULL DESC,id DESC");$s->execute([$userEmail]);$wardrobeItems=$s->fetchAll();
    $pack=wardrobeProgress($pdo,$userEmail);$checklist=$pack['items'];$progressPercent=$pack['percent'];
} catch(Throwable $e) {
    if($isAjax)wardrobeJson(['status'=>'error','message'=>'Wardrobe request failed. Check that MySQL is running and the seasonverse_db database exists.'],500);
    http_response_code(500);die('Database Error: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SeasonVerse | Smart Wardrobe & Travel Packing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #FDFBF7; color: #1A1A1A; overflow-x: hidden; scroll-behavior: smooth; }

        /* GLASSMORPHISM NAVBAR */
        .user-navbar { position: sticky; top: 0; z-index: 1000; background: rgba(253, 251, 247, 0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); display: flex; justify-content: space-between; align-items: center; padding: 18px 50px; border-bottom: 1px solid rgba(0, 0, 0, 0.04); box-shadow: 0 4px 30px rgba(0, 0, 0, 0.02); }
        .nav-brand { font-size: 1.3rem; font-weight: 700; font-family: serif; color: #1B2E23; }
        .nav-links { display: flex; gap: 30px; list-style: none; font-size: 0.85rem; font-weight: 500; color: #555; }
        .nav-links a { text-decoration: none; color: inherit; transition: color 0.2s; cursor: pointer; }
        .nav-links a:hover, .nav-links a.active { color: #1B2E23; font-weight: 600; }
        .nav-right-area { display: flex; align-items: center; gap: 15px; }
        .profile-letter { width: 32px; height: 32px; border-radius: 50%; background: #1B2E23; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; cursor: pointer; }

        .main-content { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }

        /* HEADER */
        .page-header { margin-bottom: 40px; }
        .page-header h1 { font-family: serif; font-size: 3rem; color: #1B2E23; line-height: 1.1; margin-bottom: 15px; }
        .page-header p { font-size: 0.9rem; color: #666; max-width: 600px; line-height: 1.6; }

        /* CONTROLS BAR */
        .controls-wrapper { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 50px; align-items: center; }
        .controls-bar { background: #FFF9F2; border: 1px solid #F4EBE1; border-radius: 20px; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 30px rgba(180,83,9,0.03); }
        .control-group label { display: block; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; color: #888; font-weight: 700; margin-bottom: 5px; }
        .control-group select { border: none; background: transparent; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.9rem; font-weight: 700; color: #1B2E23; outline: none; cursor: pointer; }
        
        .kyoto-badge-box { background: #FFF9F2; border: 1px solid #F4EBE1; border-radius: 20px; padding: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 30px rgba(180,83,9,0.03); }

        .btn-create { background: #1B2E23; color: #fff; padding: 12px 24px; border-radius: 30px; font-weight: 700; font-size: 0.75rem; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: transform 0.2s; }
        .btn-create:hover { transform: scale(1.03); }

        /* CURATED COLLECTION */
        .section-title { font-family: serif; font-size: 1.5rem; color: #1B2E23; margin-bottom: 20px; }
        .collection-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 15px; margin-bottom: 50px; }
        .collection-card { background: #fff; border-radius: 16px; padding: 10px; border: 1px solid #EAE5DD; text-align: center; cursor: pointer; transition: transform 0.2s; }
        .collection-card:hover { transform: translateY(-3px); }
        .collection-img { height: 90px; border-radius: 10px; background-size: cover; background-position: center; margin-bottom: 8px; }
        .collection-card h6 { font-size: 0.75rem; font-weight: 700; color: #1A1A1A; }

        /* COMPOSITION & CHECKLIST */
        .comp-checklist-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 50px; }
        
        .composition-box { background: #fff; border-radius: 20px; padding: 30px; border: 1px solid #EAE5DD; display: flex; gap: 20px; align-items: center; }
        .comp-img { width: 140px; height: 180px; border-radius: 14px; background-size: cover; background-position: center; flex-shrink: 0; }
        .comp-details h5 { font-size: 0.95rem; font-weight: 700; color: #1B2E23; margin-bottom: 12px; }
        .comp-item-row { display: flex; align-items: center; gap: 10px; font-size: 0.8rem; color: #555; margin-bottom: 8px; }
        .comp-num { width: 22px; height: 22px; border-radius: 50%; background: #FDE8C4; color: #B45309; font-size: 0.7rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }

        .checklist-box { background: #fff; border-radius: 20px; padding: 30px; border: 1px solid #EAE5DD; display: flex; flex-direction: column; justify-content: space-between; }
        .progress-bar-container { background: #F4EBE1; height: 8px; border-radius: 4px; overflow: hidden; margin: 15px 0 20px 0; }
        .progress-fill { background: #1B2E23; height: 100%; width: <?php echo $progressPercent; ?>%; transition: width 0.3s; }
        
        .checklist-item { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; color: #333; margin-bottom: 12px; cursor: pointer; }
        .checklist-item input { accent-color: #1B2E23; width: 16px; height: 16px; cursor: pointer; }
        .checklist-item.checked span { text-decoration: line-through; color: #888; }

        /* DRESSED FOR THE JOURNEY BANNER */
        .journey-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 50px; }
        .journey-card { height: 320px; border-radius: 20px; background-size: cover; background-position: center; padding: 25px; display: flex; flex-direction: column; justify-content: flex-end; color: #fff; position: relative; overflow: hidden; }
        .journey-card::before { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.7), transparent); }
        .journey-card h4, .journey-card p { position: relative; z-index: 2; }
        .journey-card h4 { font-family: serif; font-size: 1.3rem; margin-bottom: 4px; }
        .journey-card p { font-size: 0.75rem; color: #ddd; }

        /* FABRIC OF TRAVEL INFO BOX */
        .fabric-box { background: #FFF9F2; border: 1px solid #F4EBE1; border-radius: 24px; padding: 40px; text-align: center; margin-bottom: 50px; }
        .fabric-box h3 { font-family: serif; font-size: 1.6rem; color: #1B2E23; margin-bottom: 30px; }
        .fabric-features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; }
        .fabric-feat h5 { font-size: 0.85rem; font-weight: 700; color: #1B2E23; margin-bottom: 8px; }
        .fabric-feat p { font-size: 0.75rem; color: #666; line-height: 1.5; }

        /* HERO GLASS BANNER */
        .hero-glass-banner { width: 100%; height: 350px; border-radius: 24px; background-image: linear-gradient(to right, rgba(0,0,0,0.6), rgba(0,0,0,0.2)), url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1600&q=80'); background-size: cover; background-position: center; padding: 50px; display: flex; flex-direction: column; justify-content: center; margin-bottom: 50px; position: relative; overflow: hidden; }
        .glass-card { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); padding: 30px; border-radius: 20px; max-width: 450px; border: 1px solid rgba(255,255,255,0.3); color: #fff; }
        .glass-card h3 { font-family: serif; font-size: 2rem; margin-bottom: 10px; }
        .glass-card p { font-size: 0.8rem; color: #eee; margin-bottom: 20px; line-height: 1.5; }

        /* AI PROMPT BAR */
        .ai-bar { background: #1B2E23; border-radius: 30px; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; color: #fff; margin-bottom: 50px; }
        .ai-bar input { background: transparent; border: none; outline: none; color: #fff; font-size: 0.9rem; font-family: 'Plus Jakarta Sans', sans-serif; flex: 1; padding: 0 20px; }
        .ai-bar input::placeholder { color: #A3B899; }
        .btn-ai-send { width: 36px; height: 36px; border-radius: 50%; background: #FDE8C4; color: #1B2E23; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; }

        /* FOOTER */
        .dark-footer { background: #1B2E23; color: #fff; padding: 60px 50px 30px 50px; display: grid; grid-template-columns: 2fr 1fr 1fr 2fr; gap: 40px; }
        .footer-brand { font-family: serif; font-size: 1.5rem; margin-bottom: 15px; font-weight: 700; }
        .dark-footer p { font-size: 0.8rem; color: #A3B899; line-height: 1.6; }
        .footer-col h6 { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #A3B899; margin-bottom: 15px; font-weight: 700; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 10px; }
        .footer-col ul li a { color: #fff; text-decoration: none; font-size: 0.8rem; transition: color 0.2s; }
        .footer-col ul li a:hover { color: #FDE8C4; }
        .newsletter-box { display: flex; background: rgba(255,255,255,0.1); border-radius: 30px; padding: 5px; }
        .newsletter-box input { flex: 1; background: transparent; border: none; padding: 10px 15px; color: #fff; outline: none; font-size: 0.8rem; font-family: 'Plus Jakarta Sans', sans-serif; }
        .newsletter-box input::placeholder { color: #A3B899; }
        .newsletter-box button { background: #FDE8C4; color: #1B2E23; border: none; padding: 8px 18px; border-radius: 25px; font-weight: 700; font-size: 0.7rem; cursor: pointer; }
    
        .wardrobe-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:-5px 0 18px}
        .wardrobe-search{border:1px solid #EAE5DD;background:#fff;border-radius:24px;padding:11px 16px;font:inherit;font-size:.8rem;min-width:210px;outline-color:#1B2E23}
        .collection-card{position:relative}.collection-card .item-manage{position:absolute;top:14px;right:14px;border:0;border-radius:50%;width:30px;height:30px;background:rgba(255,255,255,.95);color:#1B2E23;cursor:pointer}
        .collection-card .item-category{font-size:.65rem;color:#888;margin-top:4px}.collection-card .collection-img{background-color:#F4EBE1}
        .wardrobe-modal{display:none;position:fixed;inset:0;background:rgba(20,30,24,.55);z-index:3000;align-items:center;justify-content:center;padding:20px}.wardrobe-modal.open{display:flex}
        .wardrobe-modal-panel{background:#FDFBF7;width:min(520px,100%);max-height:90vh;overflow:auto;border-radius:22px;padding:28px;border:1px solid #EAE5DD;box-shadow:0 20px 60px rgba(0,0,0,.2)}
        .wardrobe-modal-panel h3{font-family:serif;color:#1B2E23;font-size:1.5rem;margin-bottom:18px}.wardrobe-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .wardrobe-field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}.wardrobe-field label{font-size:.68rem;text-transform:uppercase;letter-spacing:.8px;color:#777;font-weight:700}
        .wardrobe-field input,.wardrobe-field select,.wardrobe-field textarea{width:100%;border:1px solid #EAE5DD;background:#fff;border-radius:10px;padding:11px;font:inherit;font-size:.82rem;outline-color:#1B2E23}
        .wardrobe-field textarea{min-height:70px;resize:vertical}.wardrobe-modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:10px}
        .btn-secondary{background:#fff;color:#1B2E23;border:1px solid #EAE5DD;padding:11px 18px;border-radius:25px;font-weight:700;cursor:pointer}
        .checklist-tools{display:flex;gap:8px;margin-top:15px}.checklist-tools input{flex:1;min-width:0;border:1px solid #EAE5DD;border-radius:20px;padding:10px 14px;font:inherit;font-size:.78rem}
        .checklist-remove{margin-left:auto;border:0;background:transparent;color:#aaa;cursor:pointer;padding:4px 7px}.inline-status{font-size:.75rem;color:#1B2E23;min-height:18px;margin-top:8px}
        @media(max-width:900px){.user-navbar{padding:16px 22px}.nav-links{gap:14px;flex-wrap:wrap}.controls-wrapper{grid-template-columns:1fr}.collection-grid{grid-template-columns:repeat(3,1fr)}.dark-footer{grid-template-columns:1fr 1fr;padding:40px 25px}}
        @media(max-width:620px){.user-navbar{align-items:flex-start;gap:12px;flex-direction:column}.nav-links{font-size:.75rem;gap:10px}.nav-right-area{width:100%;justify-content:flex-end}.page-header h1{font-size:2.3rem}.controls-bar{align-items:flex-start;gap:18px;flex-direction:column;padding:20px}.controls-bar>div{flex-wrap:wrap;gap:16px!important}.collection-grid{grid-template-columns:repeat(2,1fr)}.comp-checklist-grid,.journey-grid,.fabric-features{grid-template-columns:1fr}.composition-box{padding:18px;gap:12px}.comp-img{width:105px;height:145px}.hero-glass-banner{padding:20px;height:300px}.glass-card{padding:20px}.dark-footer{grid-template-columns:1fr}.wardrobe-form-grid{grid-template-columns:1fr}}

    </style>
</head>
<body>

<nav class="user-navbar">
        <div class="nav-brand">SeasonVerse</div>
        <ul class="nav-links">
            <li><a href="user-home.php">Home</a></li>
            <li><a href="user-ai.php">AI Planner</a></li>
            <li><a href="user-destinations.php">Destinations</a></li>
            <li><a href="user-experiences.php">Experiences</a></li>
            <li><a href="user-wardrobe.php"class="active">Smart Wardrobe</a></li>
            <li><a href="user-travel.php">Travel & Packing</a></li>
        </ul>
        <div class="nav-right-area">
    <span style="font-size: 0.85rem; font-weight: 600;"><?php echo htmlspecialchars($userName); ?></span>
    <div class="profile-letter" onclick="window.location.href='user-profile.php'" title="View Profile"><?php echo htmlspecialchars($userInitial); ?></div>
    
    <!-- CHANGE login.php TO MATCH YOUR ACTUAL LOGIN FILE (e.g. index.php) -->
    <a href="/SeasonVerse2/logout.php" style="font-size: 0.75rem; color: #C53030; text-decoration: none; font-weight: 700; margin-left: 10px;">LOGOUT</a>
</div>
    </nav>

    <div class="main-content">
        <!-- HEADER -->
        <div class="page-header">
            <h1>Pack Smart.<br>Dress for the Season.</h1>
            <p>Our intelligent packing list builder analyzes your destination weather and seasonal itinerary to bring you the ideal wardrobe for your journey.</p>
        </div>

        <!-- CONTROLS BAR & KYOTO BADGE -->
        <div class="controls-wrapper">
            <div class="controls-bar">
                <div style="display: flex; gap: 25px; align-items: center;">
                    <div class="control-group">
                        <label>Destination</label>
                        <select id="destSelect">
                            <option value="Swiss Alps">Swiss Alps</option><option value="Kyoto">Kyoto</option><option value="Maldives">Maldives</option><option value="Coorg">Coorg</option><option value="Santorini">Santorini</option><option value="Rajkot">Rajkot</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label>Season</label>
                        <select id="seasonSelect">
                            <option>Winter</option>
                            <option>Spring</option>
                            <option>Summer</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label>Style</label>
                        <select id="styleSelect">
                            <option>Minimalist</option>
                            <option>Formal</option>
                        </select>
                    </div>
                </div>
                <button class="btn-create" onclick="createWardrobe()">Create My Wardrobe</button>
            </div>

            <div class="kyoto-badge-box">
                <div>
                    <span style="font-size: 0.65rem; font-weight: 700; color: #B45309; text-transform: uppercase; letter-spacing: 1px;">Journey Today</span>
                    <h5 style="font-family: serif; font-size: 1.1rem; color: #1B2E23; margin-top: 2px;">Kyoto Tea Ceremony</h5>
                    <p style="font-size: 0.7rem; color: #666; margin-top: 2px;">Smart layering recommended</p>
                </div>
                <i class="fa-solid fa-seedling" style="font-size: 1.8rem; color: #C5A880;"></i>
            </div>
        </div>

        <!-- CURATED COLLECTION -->
        <div class="section-title">Your Curated Collection</div>
        <div class="wardrobe-actions">
            <input class="wardrobe-search" id="wardrobeSearch" type="search" placeholder="Search your wardrobe..." aria-label="Search wardrobe">
            <select class="wardrobe-search" id="categoryFilter" aria-label="Filter category"><option value="">All categories</option><option>Tops</option><option>Bottoms</option><option>Outerwear</option><option>Footwear</option><option>Accessories</option><option>Dresses</option><option>Activewear</option></select>
            <button class="btn-create" type="button" onclick="openWardrobeModal()">+ Add Item</button>
        </div>
        <div class="collection-grid" id="wardrobeGrid">
            <?php foreach($wardrobeItems as $item): ?>
                <div class="collection-card" data-title="<?php echo htmlspecialchars(strtolower($item['title']),ENT_QUOTES); ?>" data-category="<?php echo htmlspecialchars($item['category'],ENT_QUOTES); ?>">
                    <div class="collection-img" <?php if(!empty($item['image_url'])): ?>style="background-image:url('<?php echo htmlspecialchars($item['image_url'],ENT_QUOTES); ?>')"<?php endif; ?>></div>
                    <?php if(!empty($item['user_email']) && $item['user_email']===$userEmail): ?>
                    <button class="item-manage" type="button" aria-label="Edit item" onclick='editWardrobeItem(<?php echo json_encode($item,JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP); ?>)'><i class="fa-solid fa-pen"></i></button>
                    <?php endif; ?>
                    <h6><?php echo htmlspecialchars($item['title']); ?></h6><div class="item-category"><?php echo htmlspecialchars($item['category']); ?> · <?php echo htmlspecialchars($item['season']??'All Season'); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- COMPOSITION & CHECKLIST -->
        <div class="comp-checklist-grid">
            
            <div class="composition-box">
                <div class="comp-img" style="background-image: url('https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&w=400&q=80');"></div>
                <div class="comp-details">
                    <h5>Today's Composition</h5>
                    <div class="comp-item-row"><div class="comp-num">01</div> Cashmere Knit Weather</div>
                    <div class="comp-item-row"><div class="comp-num">02</div> Alpine Wool Coat</div>
                    <div class="comp-item-row"><div class="comp-num">03</div> Leather Boots</div>
                </div>
            </div>

            <div class="checklist-box">
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 700; color: #888;">
                        <span>PACKING LIST PROGRESS</span>
                        <span id="progressText"><?php echo $progressPercent; ?>% PACKED</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-fill" id="progressBar" style="width: <?php echo (int)$progressPercent; ?>%;"></div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <?php foreach($checklist as $chk): ?>
                            <label class="checklist-item <?php echo $chk['is_packed'] ? 'checked' : ''; ?>" data-check-id="<?php echo (int)$chk['id']; ?>">
                                <input type="checkbox" <?php echo $chk['is_packed'] ? 'checked' : ''; ?> onchange="toggleCheck(this, <?php echo (int)$chk['id']; ?>)">
                                <span><?php echo htmlspecialchars($chk['item_name']); ?></span>
                                <button type="button" class="checklist-remove" aria-label="Remove item" onclick="event.preventDefault();removeCheck(<?php echo (int)$chk['id']; ?>)"><i class="fa-solid fa-xmark"></i></button>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="checklist-tools"><input id="newCheckItem" type="text" maxlength="150" placeholder="Add an item to pack..."><button class="btn-secondary" type="button" onclick="addCheck()">Add</button></div>
                    <div class="inline-status" id="checkStatus" aria-live="polite"></div>
                </div>
                <button class="btn-create" style="width: 100%; margin-top: 20px; text-align: center;" onclick="saveChecklist()">Save Checklist</button>
            </div>

        </div>

        <!-- DRESSED FOR THE JOURNEY -->
        <div class="section-title" style="text-align: center; margin-bottom: 30px;">Dressed for the Journey</div>
        <div class="journey-grid">
            <div class="journey-card" style="background-image: url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&q=80');">
                <h4>Coastal Escape</h4>
                <p>Santorini, Greece</p>
            </div>
            <div class="journey-card" style="background-image: url('https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=600&q=80');">
                <h4>Misty Trails</h4>
                <p>Coorg, India</p>
            </div>
            <div class="journey-card" style="background-image: url('https://images.unsplash.com/photo-1531366936337-7c912a4589a7?auto=format&fit=crop&w=600&q=80');">
                <h4>Alpine Refuge</h4>
                <p>Zermatt, Switzerland</p>
            </div>
        </div>

        <!-- THE FABRIC OF TRAVEL -->
        <div class="fabric-box">
            <h3>The Fabric of Travel</h3>
            <div class="fabric-features">
                <div class="fabric-feat">
                    <h5><i class="fa-solid fa-feather" style="color: #B45309; margin-right: 6px;"></i> Featherweight</h5>
                    <p>Designed for lightweight mobility without sacrificing thermal warmth.</p>
                </div>
                <div class="fabric-feat">
                    <h5><i class="fa-solid fa-water" style="color: #B45309; margin-right: 6px;"></i> Adaptive Weave</h5>
                    <p>Resistant to changing climates, humidity, and seasonal precipitation.</p>
                </div>
                <div class="fabric-feat">
                    <h5><i class="fa-solid fa-seedling" style="color: #B45309; margin-right: 6px;"></i> Eco-Crafted</h5>
                    <p>Sustainably sourced fibers crafted for conscious modern explorers.</p>
                </div>
            </div>
        </div>

        <!-- HERO GLASS BANNER -->
        <div class="hero-glass-banner">
            <div class="glass-card">
                <h3>Pack Less.<br>Travel Better.</h3>
                <p>Let our intelligent algorithms curate exactly what you need based on your itinerary length and local forecast.</p>
                <button class="btn-create" style="background: #fff; color: #1B2E23;" onclick="document.getElementById('wardrobeSearch').focus(); document.getElementById('wardrobeGrid').scrollIntoView({behavior:'smooth'});">Explore Catalog</button>
            </div>
        </div>


    </div>

    <footer class="dark-footer">
        <div>
            <div class="footer-brand">SeasonVerse</div>
            <p>Curating temporal luxury through seasonal discovery and conscious exploration.</p>
        </div>
        <div class="footer-col">
            <h6>Company</h6>
            <ul>
                <li><a href="#">Seasonal Manifesto</a></li>
                <li><a href="#">Press Enquiries</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h6>Legal</h6>
            <ul>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Service</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h6>Newsletter</h6>
            <form class="newsletter-box" id="newsletterForm" onsubmit="subscribeNewsletter(event)">
                <input id="newsletterEmail" type="email" name="email" placeholder="Email address" aria-label="Email address" required>
                <button type="submit">JOIN</button>
            </form>
            <div class="inline-status" id="newsletterStatus" aria-live="polite"></div>
        </div>
    </footer>

    <div class="wardrobe-modal" id="wardrobeModal" role="dialog" aria-modal="true" aria-labelledby="wardrobeModalTitle">
        <div class="wardrobe-modal-panel"><h3 id="wardrobeModalTitle">Add to Your Wardrobe</h3>
            <form id="wardrobeForm" onsubmit="submitWardrobeItem(event)">
                <input type="hidden" id="wardrobeItemId">
                <div class="wardrobe-field"><label for="itemTitle">Item name *</label><input id="itemTitle" maxlength="100" required placeholder="e.g. Linen shirt"></div>
                <div class="wardrobe-form-grid">
                    <div class="wardrobe-field"><label for="itemCategory">Category *</label><select id="itemCategory" required><option value="">Choose category</option><option>Tops</option><option>Bottoms</option><option>Outerwear</option><option>Footwear</option><option>Accessories</option><option>Dresses</option><option>Activewear</option></select></div>
                    <div class="wardrobe-field"><label for="itemSeason">Season</label><select id="itemSeason"><option>All Season</option><option>Spring</option><option>Summer</option><option>Monsoon</option><option>Autumn</option><option>Winter</option></select></div>
                </div>
                <div class="wardrobe-form-grid">
                    <div class="wardrobe-field"><label for="itemColor">Colour</label><input id="itemColor" maxlength="40" placeholder="e.g. Olive"></div>
                    <div class="wardrobe-field"><label for="itemImage">Image URL</label><input id="itemImage" type="url" maxlength="500" placeholder="https://..."></div>
                </div>
                <div class="wardrobe-field"><label for="itemNotes">Notes</label><textarea id="itemNotes" maxlength="255" placeholder="Fabric, fit, occasion..."></textarea></div>
                <div class="wardrobe-modal-actions"><button class="btn-secondary" type="button" onclick="closeWardrobeModal()">Cancel</button><button class="btn-create" id="wardrobeSubmit" type="submit">Save Item</button><button class="btn-secondary" id="wardrobeDelete" type="button" style="display:none;color:#B42318" onclick="deleteWardrobeItem()">Delete</button></div>
                <div class="inline-status" id="wardrobeStatus" aria-live="polite"></div>
            </form>
        </div>
    </div>

    <script>
        const WARDROBE_CSRF = <?php echo json_encode($csrf); ?>;
        const WARDROBE_ENDPOINT = window.location.pathname;
        async function wardrobeRequest(action, values={}) {
            const body=new URLSearchParams({action,csrf:WARDROBE_CSRF,...values});
            const response=await fetch(WARDROBE_ENDPOINT,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body:body.toString(),credentials:'same-origin'});
            let data;try{data=await response.json()}catch(e){throw new Error('Server returned an invalid response. Check the PHP error log in XAMPP.')}
            if(!response.ok||data.status!=='success')throw new Error(data.message||'Unable to complete this action.');return data;
        }
        function status(id,msg,error=false){const el=document.getElementById(id);if(el){el.textContent=msg;el.style.color=error?'#B42318':'#1B2E23';}}
        function updateProgress(data){
            document.getElementById('progressBar').style.width=(data.percent||0)+'%';
            document.getElementById('progressText').textContent=(data.percent||0)+'% PACKED';
            const list=document.querySelector('.checklist-box [style*="flex-direction: column"]');if(!list||!Array.isArray(data.items))return;
            list.innerHTML='';data.items.forEach(item=>{
                const label=document.createElement('label');label.className='checklist-item'+(Number(item.is_packed)?' checked':'');
                const box=document.createElement('input');box.type='checkbox';box.checked=Number(item.is_packed)===1;box.addEventListener('change',()=>toggleCheck(box,Number(item.id)));
                const span=document.createElement('span');span.textContent=item.item_name;
                const del=document.createElement('button');del.type='button';del.className='checklist-remove';del.setAttribute('aria-label','Remove item');del.innerHTML='<i class="fa-solid fa-xmark"></i>';del.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();removeCheck(Number(item.id));});
                label.append(box,span,del);list.appendChild(label);
            });
        }
        async function toggleCheck(box,id){
            const previous=!box.checked;box.disabled=true;
            try{const d=await wardrobeRequest('toggle_check',{item_id:String(id),status:box.checked?'1':'0'});updateProgress(d);status('checkStatus','Packing progress saved.');}
            catch(e){box.checked=previous;status('checkStatus',e.message,true);}finally{box.disabled=false;}
        }
        async function addCheck(){const input=document.getElementById('newCheckItem'),value=input.value.trim();if(!value){status('checkStatus','Enter an item name.',true);return;}
            try{const d=await wardrobeRequest('add_check',{item_name:value});updateProgress(d);input.value='';status('checkStatus','Item added to your packing list.');}catch(e){status('checkStatus',e.message,true);}}
        async function removeCheck(id){try{const d=await wardrobeRequest('remove_check',{id:String(id)});updateProgress(d);status('checkStatus','Item removed.');}catch(e){status('checkStatus',e.message,true);}}
        async function saveChecklist(){try{const d=await wardrobeRequest('save_checklist');updateProgress(d);status('checkStatus','Your packing checklist is saved.');}catch(e){status('checkStatus',e.message,true);}}
        async function createWardrobe(){const btn=document.querySelector('.controls-bar .btn-create');btn.disabled=true;btn.textContent='CREATING...';
            try{const d=await wardrobeRequest('generate_wardrobe',{destination:document.getElementById('destSelect').value,season:document.getElementById('seasonSelect').value,style:document.getElementById('styleSelect').value});updateProgress(d);status('checkStatus',d.message);document.querySelector('.checklist-box').scrollIntoView({behavior:'smooth',block:'center'});}
            catch(e){status('checkStatus',e.message,true);}finally{btn.disabled=false;btn.textContent='Create My Wardrobe';}}
        function openWardrobeModal(){document.getElementById('wardrobeForm').reset();document.getElementById('wardrobeItemId').value='';document.getElementById('wardrobeModalTitle').textContent='Add to Your Wardrobe';document.getElementById('wardrobeSubmit').textContent='Save Item';document.getElementById('wardrobeDelete').style.display='none';status('wardrobeStatus','');document.getElementById('wardrobeModal').classList.add('open');document.body.style.overflow='hidden';document.getElementById('itemTitle').focus();}
        function closeWardrobeModal(){document.getElementById('wardrobeModal').classList.remove('open');document.body.style.overflow='';}
        function editWardrobeItem(item){openWardrobeModal();document.getElementById('wardrobeItemId').value=item.id;document.getElementById('itemTitle').value=item.title||'';document.getElementById('itemCategory').value=item.category||'';document.getElementById('itemSeason').value=item.season||'All Season';document.getElementById('itemColor').value=item.color||'';document.getElementById('itemImage').value=item.image_url||'';document.getElementById('itemNotes').value=item.notes||'';document.getElementById('wardrobeModalTitle').textContent='Edit Wardrobe Item';document.getElementById('wardrobeSubmit').textContent='Update Item';document.getElementById('wardrobeDelete').style.display='inline-block';}
        async function submitWardrobeItem(e){e.preventDefault();const id=document.getElementById('wardrobeItemId').value;
            const values={id,title:document.getElementById('itemTitle').value.trim(),category:document.getElementById('itemCategory').value,season:document.getElementById('itemSeason').value,color:document.getElementById('itemColor').value.trim(),image_url:document.getElementById('itemImage').value.trim(),notes:document.getElementById('itemNotes').value.trim()};
            const btn=document.getElementById('wardrobeSubmit');btn.disabled=true;
            try{const d=await wardrobeRequest(id?'update_wardrobe':'add_wardrobe',values);renderWardrobe(d.items);closeWardrobeModal();}catch(err){status('wardrobeStatus',err.message,true);}finally{btn.disabled=false;}}
        async function deleteWardrobeItem(){const id=document.getElementById('wardrobeItemId').value;if(!id)return;
            try{await wardrobeRequest('delete_wardrobe',{id});closeWardrobeModal();window.location.reload();}catch(e){status('wardrobeStatus',e.message,true);}}
        function renderWardrobe(items){const grid=document.getElementById('wardrobeGrid');grid.innerHTML='';items.forEach(item=>{
            const card=document.createElement('div');card.className='collection-card';card.dataset.title=(item.title||'').toLowerCase();card.dataset.category=item.category||'';
            const img=document.createElement('div');img.className='collection-img';if(item.image_url)img.style.backgroundImage='url('+JSON.stringify(item.image_url)+')';
            let edit=null;if(item.user_email){edit=document.createElement('button');edit.type='button';edit.className='item-manage';edit.setAttribute('aria-label','Edit '+item.title);edit.innerHTML='<i class="fa-solid fa-pen"></i>';edit.addEventListener('click',()=>editWardrobeItem(item));}
            const title=document.createElement('h6');title.textContent=item.title;const meta=document.createElement('div');meta.className='item-category';meta.textContent=(item.category||'')+' · '+(item.season||'All Season');if(edit)card.append(img,edit,title,meta);else card.append(img,title,meta);grid.appendChild(card);
        });filterWardrobe();}
        function filterWardrobe(){const term=document.getElementById('wardrobeSearch').value.toLowerCase(),cat=document.getElementById('categoryFilter').value;
            document.querySelectorAll('#wardrobeGrid .collection-card').forEach(c=>c.style.display=c.dataset.title.includes(term)&&(!cat||c.dataset.category===cat)?'':'none');}
        document.getElementById('wardrobeSearch')?.addEventListener('input',filterWardrobe);document.getElementById('categoryFilter')?.addEventListener('change',filterWardrobe);
        document.getElementById('newCheckItem')?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();addCheck();}});
        document.getElementById('wardrobeModal')?.addEventListener('click',e=>{if(e.target.id==='wardrobeModal')closeWardrobeModal();});
        document.addEventListener('keydown',e=>{if(e.key==='Escape')closeWardrobeModal();});
        async function subscribeNewsletter(e){e.preventDefault();const email=document.getElementById('newsletterEmail').value.trim();const btn=document.querySelector('#newsletterForm button');btn.disabled=true;
            try{const d=await wardrobeRequest('subscribe_newsletter',{email});status('newsletterStatus',d.message);document.getElementById('newsletterEmail').value='';}
            catch(err){status('newsletterStatus',err.message,true);}finally{btn.disabled=false;}}
        function sendToAI(){const v=document.getElementById('aiInput')?.value.trim();if(v)window.location.href='user-ai.php?auto_dest='+encodeURIComponent(v)+'&prompt='+encodeURIComponent('Create a travel wardrobe and packing plan for '+v);}
    </script>
</body>
</html>
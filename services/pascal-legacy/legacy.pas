program legacy;

{$mode objfpc}{$H+}

uses
  SysUtils, DateUtils, Classes, PQConnection, SQLDB;

type
  TTelemetryRecord = record
    RecordedAt: TDateTime;
    Voltage: Double;
    Temp: Double;
    SourceFile: String;
  end;

var
  DBConn: TPQConnection;
  SQLQuery: TSQLQuery;
  SQLTransaction: TSQLTransaction;
  CSVOutDir: String;

function ConnectToDB: Boolean;
var
  Host, Port, User, Pass, DBName: String;
begin
  Result := False;
  
  Host := GetEnvironmentVariable('PGHOST');
  Port := GetEnvironmentVariable('PGPORT');
  User := GetEnvironmentVariable('PGUSER');
  Pass := GetEnvironmentVariable('PGPASSWORD');
  DBName := GetEnvironmentVariable('PGDATABASE');
  
  if (Host = '') or (Port = '') or (User = '') or (DBName = '') then
  begin
    WriteLn(StdErr, '[ERROR] Missing PostgreSQL environment variables');
    Exit;
  end;
  
  try
    DBConn := TPQConnection.Create(nil);
    DBConn.HostName := Host;
    DBConn.DatabaseName := DBName;
    DBConn.UserName := User;
    DBConn.Password := Pass;
    DBConn.Port := StrToInt(Port);
    
    SQLTransaction := TSQLTransaction.Create(nil);
    SQLTransaction.DataBase := DBConn;
    DBConn.Transaction := SQLTransaction;
    
    SQLQuery := TSQLQuery.Create(nil);
    SQLQuery.DataBase := DBConn;
    SQLQuery.Transaction := SQLTransaction;
    
    DBConn.Open;
    WriteLn('[INFO] ✅ Connected to PostgreSQL');
    Result := True;
  except
    on E: Exception do
    begin
      WriteLn(StdErr, '[ERROR] ❌ DB connection failed: ', E.Message);
      Result := False;
    end;
  end;
end;

function GenerateTelemetry: TTelemetryRecord;
begin
  Randomize;
  Result.RecordedAt := Now;
  Result.Voltage := 12.5 + Random * 0.5;
  Result.Temp := 20.0 + Random * 5.0;
  Result.SourceFile := 'pascal-legacy';
end;

function SaveToDB(const Rec: TTelemetryRecord): Boolean;
begin
  Result := False;
  try
    SQLQuery.SQL.Text := 
      'INSERT INTO telemetry_legacy (recorded_at, voltage, temp, source_file) ' +
      'VALUES (:recorded_at, :voltage, :temp, :source_file)';
    SQLQuery.ParamByName('recorded_at').AsDateTime := Rec.RecordedAt;
    SQLQuery.ParamByName('voltage').AsFloat := Rec.Voltage;
    SQLQuery.ParamByName('temp').AsFloat := Rec.Temp;
    SQLQuery.ParamByName('source_file').AsString := Rec.SourceFile;
    SQLQuery.ExecSQL;
    SQLTransaction.Commit;
    
    WriteLn('[INFO] ✅ Saved to DB: voltage=', Rec.Voltage:0:2, 'V, temp=', Rec.Temp:0:2, '°C');
    Result := True;
  except
    on E: Exception do
    begin
      WriteLn(StdErr, '[ERROR] ❌ DB save failed: ', E.Message);
      SQLTransaction.Rollback;
    end;
  end;
end;

function SaveToCSV(const Rec: TTelemetryRecord): Boolean;
var
  CSVFile: TextFile;
  FileName: String;
  FileExists: Boolean;
begin
  Result := False;
  FileName := CSVOutDir + '/telemetry_' + FormatDateTime('yyyymmdd', Rec.RecordedAt) + '.csv';
  FileExists := SysUtils.FileExists(FileName);
  
  try
    AssignFile(CSVFile, FileName);
    if FileExists then
      Append(CSVFile)
    else
      Rewrite(CSVFile);
    
    if not FileExists then
      WriteLn(CSVFile, 'Timestamp,Voltage,Temperature,SourceFile');
    
    WriteLn(CSVFile, 
      FormatDateTime('yyyy-mm-dd hh:nn:ss', Rec.RecordedAt), ',',
      Rec.Voltage:0:2, ',',
      Rec.Temp:0:2, ',',
      '"', Rec.SourceFile, '"'
    );
    
    CloseFile(CSVFile);
    WriteLn('[INFO] ✅ Saved to CSV: ', FileName);
    Result := True;
  except
    on E: Exception do
    begin
      WriteLn(StdErr, '[ERROR] ❌ CSV save failed: ', E.Message);
    end;
  end;
end;

var
  Record: TTelemetryRecord;
begin
  WriteLn('[INFO] 🚀 Pascal Legacy: Single generation mode');
  
  CSVOutDir := GetEnvironmentVariable('CSV_OUT_DIR');
  if CSVOutDir = '' then
    CSVOutDir := '/data/csv';
  
  if not DirectoryExists(CSVOutDir) then
    ForceDirectories(CSVOutDir);
  
  if not ConnectToDB then
  begin
    WriteLn(StdErr, '[ERROR] ❌ Failed to connect to DB');
    Halt(1);
  end;
  
  Record := GenerateTelemetry;
  
  if not SaveToDB(Record) then
    WriteLn(StdErr, '[WARN] ⚠️  DB save failed');
  
  if not SaveToCSV(Record) then
    WriteLn(StdErr, '[WARN] ⚠️  CSV save failed');
  
  SQLQuery.Free;
  SQLTransaction.Free;
  DBConn.Free;
  
  WriteLn('[INFO] ✅ Generation complete');
end.

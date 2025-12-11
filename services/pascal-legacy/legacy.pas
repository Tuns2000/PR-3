program legacy;

{$mode objfpc}{$H+}

uses
  SysUtils, DateUtils, Math, PQConnection, SQLDB;

type
  TTelemetryRecord = record
    RecordedAt: TDateTime;
    Voltage: Double;
    Temp: Double;
    SourceFile: String;
  end;

var
  Connection: TPQConnection;
  Transaction: TSQLTransaction;
  Query: TSQLQuery;
  CSVFile: Text;
  CSVFilePath: String;
  CSVOutDir: String;
  TelemetryData: TTelemetryRecord;
  PGHost, PGPort, PGUser, PGPassword, PGDatabase: String;

procedure LogInfo(const Msg: String);
begin
  WriteLn('[INFO] ', Msg);
end;

procedure LogError(const Msg: String);
begin
  WriteLn(StdErr, '[ERROR] ', Msg);
end;

procedure LogWarn(const Msg: String);
begin
  WriteLn(StdErr, '[WARN] ', Msg);
end;

function ConnectToDatabase: Boolean;
begin
  Result := False;
  try
    Connection := TPQConnection.Create(nil);
    Transaction := TSQLTransaction.Create(nil);
    Query := TSQLQuery.Create(nil);

    Connection.HostName := PGHost;
    Connection.DatabaseName := PGDatabase;
    Connection.UserName := PGUser;
    Connection.Password := PGPassword;
    
    if PGPort <> '' then
      Connection.Params.Add('port=' + PGPort);

    Transaction.DataBase := Connection;
    Query.DataBase := Connection;
    Query.Transaction := Transaction;

    Connection.Open;
    Transaction.Active := True;

    LogInfo('Connected to PostgreSQL');
    Result := True;
  except
    on E: Exception do
    begin
      LogError('Database connection failed: ' + E.Message);
      Result := False;
    end;
  end;
end;

procedure DisconnectFromDatabase;
begin
  try
    if Assigned(Query) then
      Query.Free;
    if Assigned(Transaction) then
    begin
      if Transaction.Active then
        Transaction.Commit;
      Transaction.Free;
    end;
    if Assigned(Connection) then
    begin
      if Connection.Connected then
        Connection.Close;
      Connection.Free;
    end;
    LogInfo('Disconnected from PostgreSQL');
  except
    on E: Exception do
      LogError('Disconnect error: ' + E.Message);
  end;
end;

procedure GenerateTelemetryData(var Data: TTelemetryRecord);
begin
  Data.RecordedAt := Now;
  Data.Voltage := 12.5 + Random * 0.5;
  Data.Temp := 20.0 + Random * 5.0;
  Data.SourceFile := 'pascal-legacy';
end;

function SaveToDatabase(const Data: TTelemetryRecord): Boolean;
var
  SQL: String;
begin
  Result := False;
  try
    SQL := 'INSERT INTO telemetry_legacy (recorded_at, voltage, temp, source_file) ' +
           'VALUES (:recorded_at, :voltage, :temp, :source_file)';

    Query.Close;
    Query.SQL.Clear;
    Query.SQL.Add(SQL);
    Query.ParamByName('recorded_at').AsDateTime := Data.RecordedAt;
    Query.ParamByName('voltage').AsFloat := Data.Voltage;
    Query.ParamByName('temp').AsFloat := Data.Temp;
    Query.ParamByName('source_file').AsString := Data.SourceFile;
    Query.ExecSQL;

    Transaction.Commit;

    LogInfo(Format('Saved to DB: voltage=%.2fV, temp=%.2f°C', [Data.Voltage, Data.Temp]));
    Result := True;
  except
    on E: Exception do
    begin
      LogError('Database insert failed: ' + E.Message);
      try
        Transaction.Rollback;
      except
      end;
      Result := False;
    end;
  end;
end;

function SaveToCSV(const Data: TTelemetryRecord): Boolean;
var
  DateStr: String;
  UnixTimestamp: Int64;
  CSVExists: Boolean;
  IsActive: Boolean;
begin
  Result := False;
  try
    DateStr := FormatDateTime('yyyymmdd', Data.RecordedAt);
    CSVFilePath := CSVOutDir + '/telemetry_' + DateStr + '.csv';

    CSVExists := SysUtils.FileExists(CSVFilePath);

    Assign(CSVFile, CSVFilePath);
    if CSVExists then
      Append(CSVFile)
    else
      Rewrite(CSVFile);

    { Header with data types - only for new files }
    if not CSVExists then
      WriteLn(CSVFile, 'Timestamp,UnixTimestamp,Voltage,Temperature,IsActive,SourceFile');

    { Convert datetime to Unix timestamp }
    UnixTimestamp := DateTimeToUnix(Data.RecordedAt);
    
    { Determine active status (example: voltage > 12.7 = active) }
    IsActive := Data.Voltage > 12.7;

    { Data row with proper types:
      - Timestamp: ISO 8601 format (string)
      - UnixTimestamp: integer (numeric)
      - Voltage: float with 3 decimals (numeric)
      - Temperature: float with 2 decimals (numeric)
      - IsActive: boolean as TRUE/FALSE (logical)
      - SourceFile: string with quotes (text) }
    WriteLn(CSVFile, Format('%s,%d,%.3f,%.2f,%s,"%s"',
      [FormatDateTime('yyyy-mm-dd"T"hh:nn:ss"Z"', Data.RecordedAt),
       UnixTimestamp,
       Data.Voltage,
       Data.Temp,
       BoolToStr(IsActive, 'TRUE', 'FALSE'),
       Data.SourceFile]));

    Close(CSVFile);

    LogInfo(Format('CSV: timestamp=%d, active=%s', [UnixTimestamp, BoolToStr(IsActive, 'TRUE', 'FALSE')]));
    Result := True;
  except
    on E: Exception do
    begin
      LogError('CSV write failed: ' + E.Message);
      try
        Close(CSVFile);
      except
      end;
      Result := False;
    end;
  end;
end;

procedure Run;
var
  DBConnected: Boolean;
begin
  LogInfo('Starting telemetry generation...');

  PGHost := GetEnvironmentVariable('PGHOST');
  PGPort := GetEnvironmentVariable('PGPORT');
  PGUser := GetEnvironmentVariable('PGUSER');
  PGPassword := GetEnvironmentVariable('PGPASSWORD');
  PGDatabase := GetEnvironmentVariable('PGDATABASE');
  CSVOutDir := GetEnvironmentVariable('CSV_OUT_DIR');

  if CSVOutDir = '' then
    CSVOutDir := '/data/csv';

  if (PGHost = '') or (PGUser = '') or (PGDatabase = '') then
  begin
    LogError('Missing required environment variables (PGHOST, PGUSER, PGDATABASE)');
    Halt(1);
  end;

  if not DirectoryExists(CSVOutDir) then
  begin
    if not CreateDir(CSVOutDir) then
    begin
      LogError('Failed to create CSV directory: ' + CSVOutDir);
      Halt(1);
    end;
  end;

  DBConnected := ConnectToDatabase;

  GenerateTelemetryData(TelemetryData);

  if DBConnected then
  begin
    if not SaveToDatabase(TelemetryData) then
      LogWarn('Failed to save to database, continuing...');
  end
  else
    LogWarn('Skipping database save (not connected)');

  if not SaveToCSV(TelemetryData) then
  begin
    LogError('Failed to save to CSV');
    if DBConnected then
      DisconnectFromDatabase;
    Halt(1);
  end;

  if DBConnected then
    DisconnectFromDatabase;

  LogInfo('Generation complete');
end;

begin
  Randomize;
  Run;
end.
